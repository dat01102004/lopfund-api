<?php
// app/Services/PaymentAutoVerifier.php
namespace App\Services;

use App\Models\{Payment, FundAccount};
use Illuminate\Support\Str;

class PaymentAutoVerifier
{
    public function decide(Payment $payment, array $ocr, ?FundAccount $fund): array
    {
        $expect = (int) $payment->amount;
        $found  = (int) ($ocr['amount'] ?? 0);

        // ---- Amount check (hard) ----
        $absTol = (int)  (config('ai.payment_verify.amount_tolerance_abs', 0));
        $pctTol = (float) (config('ai.payment_verify.amount_tolerance_pct', 0.0));
        if (!$this->amountOk($expect, $found, $absTol, $pctTol)) {
            return $this->fail('AMOUNT_MISMATCH',
                "expected={$expect}, ocr={$found}, tol_abs={$absTol}, tol_pct={$pctTol}");
        }

        // ---- Payee account check (optional hard) ----
        if (config('ai.payment_verify.require_payee_match', false) && $fund && $fund->account_number) {
            $ocrAcc = trim((string)($ocr['payee_account'] ?? ''));
            if ($ocrAcc !== '') {
                // so theo 4-6 số cuối để tránh OCR sai dấu cách
                $tailLen = (int) config('ai.payment_verify.payee_tail_len', 6);
                $expectTail = Str::substr(preg_replace('/\D+/', '', $fund->account_number), -$tailLen);
                $foundTail  = Str::substr(preg_replace('/\D+/', '', $ocrAcc), -$tailLen);
                if ($expectTail === '' || $expectTail !== $foundTail) {
                    return $this->fail('PAYEE_MISMATCH', "fund_tail={$expectTail}, ocr_tail={$foundTail}");
                }
            }
        }

        // ---- Transaction ref check (optional hard) ----
        if (config('ai.payment_verify.require_txn_ref', false) && empty($ocr['txn_ref'])) {
            return $this->fail('NO_TXN_REF', 'missing txn_ref');
        }

        // ---- NOTE (transfer description) check ----
        $requireNote = (bool) config('ai.payment_verify.require_note', true);
        $note        = trim((string)($ocr['note'] ?? ''));
        $softWarn    = null;

        if ($requireNote) {
            if ($note === '') {
                return $this->fail('NO_NOTE', 'transfer note is empty');
            }

            if (!$this->noteMatch($payment, $note)) {
                return $this->fail('NOTE_MISMATCH', "note='{$note}' not matched");
            }
        } else {
            // nếu không bắt buộc thì chấm điểm mềm để log
            if ($note !== '' && !$this->noteMatch($payment, $note)) {
                $softWarn = 'NOTE_WEAK';
            }
        }

        return [
            'pass'   => true,
            'code'   => 'MATCH_OK',
            'detail' => json_encode([
                'expect'    => $expect,
                'found'     => $found,
                'txn_ref'   => $ocr['txn_ref'] ?? null,
                'note'      => $note ?: null,
                'soft_warn' => $softWarn,
            ]),
        ];
    }

    // ---------- Helpers ----------

    private function amountOk(int $expect, int $found, int $abs, float $pct): bool
    {
        if ($found <= 0) return false;
        if ($abs > 0 && abs($expect - $found) <= $abs) return true;
        if ($pct > 0) {
            $deltaPct = abs($expect - $found) / max(1, $expect);
            return $deltaPct <= $pct;
        }
        return $expect === $found;
    }

    /**
     * So khớp nội dung CK:
     * - Chuẩn hoá (bỏ dấu, lower, bỏ extra space)
     * - Kiểm tra chứa ít nhất 1 trong các token kỳ vọng
     *   + "lop {invoiceId}", "invoice {invoiceId}"
     *   + tên người nộp (không dấu)
     *   + tokens thêm từ config: ai.payment_verify.note_must_include = ['lop', 'quy', ...]
     */
    private function noteMatch(Payment $payment, string $noteRaw): bool
    {
        $norm = $this->normalize($noteRaw);

        $invoiceId = (string) $payment->invoice_id;
        $payerName = $payment->payer?->user?->name ?? '';
        $payerName = $this->normalize($payerName);

        $tokens = [
            "lop {$invoiceId}",
            "invoice {$invoiceId}",
            $payerName,
        ];

        // Bổ sung token tuỳ chỉnh từ config
        $extra = (array) config('ai.payment_verify.note_must_include', []);
        foreach ($extra as $kw) {
            $kw = $this->normalize((string) $kw);
            if ($kw !== '') $tokens[] = $kw;
        }

        // Loại token rỗng và trùng
        $tokens = array_values(array_unique(array_filter($tokens, fn($t) => $t !== '')));

        foreach ($tokens as $tk) {
            if ($tk !== '' && Str::contains($norm, $tk)) {
                return true;
            }
        }
        return false;
    }

    private function normalize(string $s): string
    {
        $s = Str::ascii($s);          // bỏ dấu
        $s = Str::lower($s);          // lower
        $s = preg_replace('/\s+/', ' ', trim($s)) ?: '';
        return $s;
    }

    private function fail(string $code, string $detail): array
    {
        return ['pass' => false, 'code' => $code, 'detail' => $detail];
    }
}
