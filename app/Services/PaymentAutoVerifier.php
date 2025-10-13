<?php
// app/Services/PaymentAutoVerifier.php
namespace App\Services;

use App\Models\{Payment, Invoice, FundAccount, ClassModel as Classroom}; // chỉnh alias theo dự án
use Illuminate\Support\Str;

class PaymentAutoVerifier
{
    public function decide(Payment $payment, array $ocr, FundAccount $fund): array
    {
        $expect = (int)$payment->amount;
        $found  = (int)($ocr['amount'] ?? 0);

        $absTol = config('ai.payment_verify.amount_tolerance_abs');
        $pctTol = config('ai.payment_verify.amount_tolerance_pct');

        $okAmount = $this->amountOk($expect, $found, $absTol, $pctTol);
        if (!$okAmount) {
            return $this->fail('AMOUNT_MISMATCH', "expected={$expect}, ocr={$found}, tol_abs={$absTol}, tol_pct={$pctTol}");
        }

        if (config('ai.payment_verify.require_payee_match')) {
            $ocrAcc = $ocr['payee_account'] ?? null;
            if ($ocrAcc && $fund->account_number) {
                if (!Str::endsWith($fund->account_number, Str::substr($ocrAcc, -6))) {
                    return $this->fail('PAYEE_MISMATCH', "fund_acc={$fund->account_number}, ocr_acc={$ocrAcc}");
                }
            }
        }

        if (config('ai.payment_verify.require_txn_ref') && empty($ocr['txn_ref'])) {
            return $this->fail('NO_TXN_REF', "missing txn_ref");
        }

        // Soft rule trên nội dung CK (nếu có)
        if (!empty($ocr['note'])) {
            $need = config('ai.payment_verify.note_must_include', []);
            $hit = false;
            foreach ($need as $kw) {
                if (Str::contains(Str::lower($ocr['note']), Str::lower($kw))) { $hit = true; break; }
            }
            // không fail cứng, chỉ log mềm
            $softWarn = $hit ? null : 'NOTE_WEAK';
        }

        return [
          'pass' => true,
          'code' => 'MATCH_OK',
          'detail' => json_encode([
            'expect'=>$expect,'found'=>$found,
            'txn_ref'=>$ocr['txn_ref'] ?? null,
            'note'=>$ocr['note'] ?? null,
            'soft_warn' => $softWarn ?? null,
          ]),
        ];
    }

    private function amountOk(int $expect, int $found, int $abs, float $pct): bool {
        if ($found === 0) return false;
        if (abs($expect - $found) <= $abs) return true;
        $deltaPct = abs($expect - $found) / max(1, $expect);
        return $deltaPct <= $pct;
    }

    private function fail(string $code, string $detail): array {
        return ['pass'=>false,'code'=>$code,'detail'=>$detail];
    }
}

