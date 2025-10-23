<?php

// database/migrations/2025_09_25_000001_add_ocr_fields_to_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('payments', function (Blueprint $t) {
      $t->text('ocr_raw')->nullable();
      $t->unsignedBigInteger('ocr_amount')->nullable();
      $t->string('ocr_txn_ref', 64)->nullable();
      $t->string('ocr_method', 32)->nullable();
      $t->unsignedTinyInteger('ocr_confidence')->nullable();

      $t->boolean('auto_verified')->default(false);
      $t->string('verify_reason_code', 64)->nullable();   // ex: MATCH_OK, AMOUNT_MISMATCH, PAYEE_MISMATCH, NO_TXN_REF, OCR_EMPTY...
      $t->text('verify_reason_detail')->nullable();       // log chi tiết rules
      // đã có: verified_by, verified_at, status (pending/verified/rejected/…)
    });
  }
  public function down(): void {
    Schema::table('payments', function (Blueprint $t) {
      $t->dropColumn([
        'ocr_raw','ocr_amount','ocr_txn_ref','ocr_method','ocr_confidence',
        'auto_verified','verify_reason_code','verify_reason_detail'
      ]);
    });
  }
};
