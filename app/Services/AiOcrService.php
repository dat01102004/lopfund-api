<?php //app\Services\AiOcrService.php

namespace App\Services;

use Illuminate\Support\Str;
// Nếu đã cài package, import namespace CHÍNH XÁC của TesseractOCR:
use thiagoalessio\TesseractOCR\TesseractOCR;

class AiOcrService
{
    /**
     * Trả về cấu trúc:
     * [
     *   'ok'         => bool,
     *   'raw_text'   => string,
     *   'amount'     => int|null,     // số tiền đọc được
     *   'date'       => string|null,  // yyyy-MM-dd nếu bắt được
     *   'method'     => string|null,  // bank/momo/zalopay...
     *   'txn_ref'    => string|null,
     *   'confidence' => int|null      // nếu có
     * ]
     */
    public function extract(string $absoluteImagePath): array
    {
        $raw = '';

        // Nếu chưa cài lib, code vẫn không crash
        try {
            if (class_exists(TesseractOCR::class)) {
                $raw = (new TesseractOCR($absoluteImagePath))
                    ->lang('vie', 'eng')
                    ->run();
            }
        } catch (\Throwable $e) {
            $raw = '';
        }

        $raw = trim($raw ?? '');

        // Fallback khi không OCR được
        if ($raw === '') {
            return [
                'ok' => false,
                'raw_text' => '',
                'amount' => null,
                'date' => null,
                'method' => null,
                'txn_ref' => null,
                'confidence' => null,
            ];
        }

        // --- Parse cơ bản số tiền (VD: 200.000 / 200000 đ / VND)
        $amount = null;
        if (preg_match('/(\d[\d\.\,]{3,})\s*(đ|vnd|vnđ)?/ui', $raw, $m)) {
            $num = preg_replace('/[^\d]/', '', $m[1]);
            if ($num !== '') $amount = (int) $num;
        }

        // Heuristics nhẹ
        $method = null;
        if (stripos($raw, 'momo') !== false) $method = 'momo';
        elseif (stripos($raw, 'zalopay') !== false) $method = 'zalopay';
        elseif (stripos($raw, 'bank') !== false || stripos($raw, 'chuyển khoản') !== false) $method = 'bank';

        $txnRef = null;
        if (preg_match('/(CK|CT|TXN|REF)[\s\-\:]*([A-Z0-9\-]{4,})/i', $raw, $mm)) {
            $txnRef = strtoupper($mm[2]);
        }

        // Date rất khó, tạm để null hoặc tự thêm regex khi cần
        return [
            'ok' => ($amount !== null),
            'raw_text' => $raw,
            'amount' => $amount,
            'date' => null,
            'method' => $method,
            'txn_ref' => $txnRef,
            'confidence' => null,
        ];
    }
}
