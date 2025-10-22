<?php
// config/ai.php
return [
  'payment_verify' => [
    'amount_tolerance_abs' => 1000,   // ±1.000đ
    'amount_tolerance_pct' => 0.01,   // ±1%
       'require_payee_match'  => false,  // so đuôi TK nhận
        'payee_tail_len'       => 6,      // số chữ số cuối để so
        'require_txn_ref'      => false,  // yêu cầu có mã GD
        'require_note'         => true,   // BẮT BUỘC có note hợp lệ
        'note_must_include'    => [],     // token bổ sung (tùy lớp)
  ],
];

