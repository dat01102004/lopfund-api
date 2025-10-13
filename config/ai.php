<?php
// config/ai.php
return [
  'payment_verify' => [
    'amount_tolerance_abs' => 1000,   // ±1.000đ
    'amount_tolerance_pct' => 0.01,   // ±1%
    'require_txn_ref'      => false,  // bật true nếu muốn bắt buộc
    'require_payee_match'  => true,   // STK phải khớp FundAccount
    'note_must_include'    => ['lop','class','invoice','hoc phi','quy lop'], // soft check
  ],
];

