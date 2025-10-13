<?php
// app/Jobs/ProcessPaymentProof.php
namespace App\Jobs;

use App\Models\{Payment, FundAccount};
use App\Services\{AiOcrService, PaymentAutoVerifier};
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessPaymentProof implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** KHÔNG khai báo public string $queue = 'payments';  */
    public int $paymentId;
    public ?string $absoluteImagePath;

    public function __construct(int $paymentId, ?string $absoluteImagePath = null)
    {
        $this->paymentId        = $paymentId;
        $this->absoluteImagePath= $absoluteImagePath;

        // ép job đi vào hàng "payments" (cách an toàn, không đụng trait)
        $this->onQueue('payments');
    }

    public function handle(AiOcrService $ocr, PaymentAutoVerifier $verifier): void
    {
        try {
            Log::info("[OCR] Start job for payment={$this->paymentId}");

            $payment = Payment::with(['invoice.cycle','invoice.feeCycle','payer.user'])
                ->find($this->paymentId);

            if (!$payment) {
                Log::warning("[OCR] Payment {$this->paymentId} not found");
                return;
            }

            if (!in_array($payment->status, ['pending','submitted'], true)) {
                Log::info("[OCR] Skip payment #{$payment->id}, status={$payment->status}");
                return;
            }

            $cycle   = $payment->invoice->cycle ?? $payment->invoice->feeCycle ?? null;
            $classId = $cycle?->class_id;
            $fund    = $classId ? FundAccount::where('class_id',$classId)->first() : null;

            $absPath = $this->resolveAbsoluteImagePath($payment);
            Log::info("[OCR] resolve path => {$absPath}");
            if (!$absPath || !is_file($absPath)) {
                $this->rejectWith($payment, 'PROOF_NOT_FOUND', 'cannot resolve proof image path');
                $this->notifyTreasurer($payment, false);
                return;
            }

            // OCR luôn bọc try/catch để KHÔNG làm fail job
            $res = [];
            try {
                $res = $ocr->extract($absPath);
            } catch (\Throwable $e) {
                Log::error("[OCR] extract error: ".$e->getMessage());
                $this->rejectWith($payment, 'OCR_ERROR', $e->getMessage());
                $this->notifyTreasurer($payment, false);
                return;
            }

            $this->saveCols($payment, [
                'ocr_raw'        => $res['raw_text'] ?? null,
                'ocr_amount'     => $res['amount'] ?? null,
                'ocr_txn_ref'    => $res['txn_ref'] ?? null,
                'ocr_method'     => $res['method'] ?? null,
                'ocr_confidence' => $res['confidence'] ?? null,
            ]);

            if (!($res['ok'] ?? false)) {
                $this->rejectWith($payment, 'OCR_EMPTY', 'no text/amount extracted');
                $this->notifyTreasurer($payment, false);
                return;
            }

            $decision = $verifier->decide($payment, [
                'amount'        => $res['amount'] ?? null,
                'txn_ref'       => $res['txn_ref'] ?? null,
                'method'        => $res['method'] ?? null,
                'payee_account' => $res['payee_account'] ?? null,
                'note'          => $res['raw_text'] ?? null,
            ], $fund);

            Log::info("[OCR] decision: ".json_encode($decision));

            if (($decision['pass'] ?? false) === true) {
                $this->approveAuto($payment, $decision['code'] ?? 'MATCH_OK', $decision['detail'] ?? null);
                $this->notifyTreasurer($payment, true);
            } else {
                $this->rejectWith($payment, $decision['code'] ?? 'AUTO_REJECT', $decision['detail'] ?? null);
                $this->notifyTreasurer($payment, false);
            }
        } catch (\Throwable $e) {
            Log::error("[OCR] job crashed: ".$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            // optional: đánh dấu payment là rejected để không kẹt
            // $this->rejectWith(Payment::find($this->paymentId), 'JOB_CRASH', $e->getMessage());
            throw $e; // vẫn để failed_jobs ghi lại
        }
    }

    private function resolveAbsoluteImagePath(Payment $p): ?string
    {
        if ($this->absoluteImagePath && is_file($this->absoluteImagePath)) {
            return $this->absoluteImagePath;
        }
        $url = (string) ($p->proof_path ?? '');
        if ($url === '') return null;

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if ($path === '') return null;

        if (str_starts_with($path, '/storage/')) {
            $rel   = ltrim(substr($path, 9), '/'); // 'storage/' dài 9
            $guess = storage_path('app/public/'.$rel);
            if (is_file($guess)) return $guess;
            $guess2 = public_path($path);
            if (is_file($guess2)) return $guess2;
        }
        $guess3 = public_path($path);
        return is_file($guess3) ? $guess3 : null;
    }

    private function approveAuto(Payment $p, string $code, ?string $detail): void
    {
        DB::transaction(function () use ($p,$code,$detail) {
            $this->saveCols($p, [
                'status'               => 'verified',
                'auto_verified'        => true,
                'verify_reason_code'   => $code,
                'verify_reason_detail' => $detail,
                'verified_by'          => null,
                'verified_at'          => Carbon::now(),
            ]);
            $p->refresh();

            $invoice = $p->invoice()->with('payments')->first();
            if ($invoice) {
                $sum = (int) $invoice->payments->where('status','verified')->sum('amount');
                if ($sum >= (int)$invoice->amount && $invoice->status !== 'verified') {
                    $invoice->update(['status'=>'verified']);
                }
            }
        });
    }

    private function rejectWith(Payment $p, string $code, ?string $detail): void
    {
    $this->saveCols($p, [
        'status'               => $p->status === 'submitted' ? 'submitted' : $p->status,
        'auto_verified'        => true,             // đã xử lý tự động
        'verify_reason_code'   => $code,            // ví dụ: OCR_EMPTY, AMOUNT_MISMATCH...
        'verify_reason_detail' => $detail,
        'verified_by'          => null,
        'verified_at'          => null,
    ]);
}

    private function notifyTreasurer(Payment $p, bool $ok): void
    {
        try {
            $cycle = $p->invoice->cycle ?? $p->invoice->feeCycle ?? null;
            $classId = $cycle?->class_id;
            if (!$classId || !Schema::hasTable('notifications')) return;

            $targets = DB::table('class_members')
                ->where('class_id',$classId)
                ->whereIn('role',['treasurer','owner'])
                ->pluck('user_id');

            foreach ($targets as $uid) {
                DB::table('notifications')->insert([
                    'user_id'    => $uid,
                    'class_id'   => $classId,
                    'type'       => $ok ? 'payment_verified' : 'payment_rejected',
                    'title'      => $ok ? 'Tự động duyệt: THÀNH CÔNG' : 'Tự động duyệt: THẤT BẠI',
                    'body'       => "Payment #{$p->id} — status={$p->status}, code={$p->verify_reason_code}",
                    'is_read'    => 0,
                    'sent_at'    => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[OCR] notifyTreasurer failed: '.$e->getMessage());
        }
    }

    private function saveCols(Payment $p, array $data): void
    {
    $table = $p->getTable();
    $filtered = [];

    foreach ($data as $col => $val) {
        try {
            if (Schema::hasColumn($table, $col)) {
                $filtered[$col] = $val;
            }
        } catch (\Throwable $e) {
            $filtered[$col] = $val;
        }
    }

    if (!empty($filtered)) {
        $p->forceFill($filtered)->save();   // <— dùng forceFill thay vì fill
    }
}
}
