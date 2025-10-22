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
        $note = null;
        $lower = mb_strtolower($raw, 'UTF-8');

        // 1) Bắt theo nhãn tiếng Việt phổ biến
        if (preg_match('/n(ô|o)i\s*dung(?:\s*ck)?\s*[:\-]\s*(.+)/ui', $lower, $m)) {
            $note = trim($m[2]);
        }
        // 2) Nhãn tiếng Anh ngân hàng: "description", "content", "reference"
        elseif (preg_match('/(description|content|reference|ref\.?)\s*[:\-]\s*(.+)/ui', $lower, $m)) {
            $note = trim($m[2]);
        }
        // 3) Phương án fallback: lấy dòng gần cụm “chuyển khoản/transfer”
        if (!$note) {
            $lines = preg_split('/\R/u', $lower);
            foreach ($lines as $i => $line) {
                if (preg_match('/(chuy[eê]n\s*kho[ảa]n|transfer|ckt|ck)/ui', $line)) {
                    $note = trim($lines[$i+1] ?? '');
                    break;
                }
            }
        }

        // Date rất khó, tạm để null hoặc tự thêm regex khi cần
        return [
            'ok' => ($amount !== null),
            'raw_text' => $raw,
            'amount' => $amount,
            'date' => null,
            'method' => $method,
            'txn_ref' => $txnRef,
            'note'       => $note,
            'confidence' => null,
        ];
    }
}
