<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $t) {
            // kỳ thu (để filter/report theo fee cycle)
            if (!Schema::hasColumn('expenses', 'fee_cycle_id')) {
                $t->unsignedBigInteger('fee_cycle_id')->nullable()->index()->after('class_id');
                // với SQLite/ MySQL đều OK; nếu SQLite không bật FK cũng không sao
                $t->foreign('fee_cycle_id')->references('id')->on('fee_cycles')->nullOnDelete();
            }
            // tiêu đề hiển thị (ledger/report đang select e.title)
            if (!Schema::hasColumn('expenses', 'title')) {
                $t->string('title', 255)->nullable()->after('fee_cycle_id');
            }
            // đường dẫn ảnh hoá đơn (ledger có thể show)
            if (!Schema::hasColumn('expenses', 'receipt_path')) {
                $t->string('receipt_path', 255)->nullable()->after('title');
            }
            // người tạo (một số bản cũ dùng created_by)
            if (!Schema::hasColumn('expenses', 'created_by')) {
                $t->unsignedBigInteger('created_by')->nullable()->index()->after('receipt_path');
            }
            // ghi chú (nếu controller của bạn có dùng)
            if (!Schema::hasColumn('expenses', 'note')) {
                $t->text('note')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $t) {
            if (Schema::hasColumn('expenses','fee_cycle_id')) {
                $t->dropForeign(['fee_cycle_id']);
                $t->dropColumn('fee_cycle_id');
            }
            if (Schema::hasColumn('expenses','title'))        $t->dropColumn('title');
            if (Schema::hasColumn('expenses','receipt_path')) $t->dropColumn('receipt_path');
            if (Schema::hasColumn('expenses','created_by'))   $t->dropColumn('created_by');
            if (Schema::hasColumn('expenses','note'))         $t->dropColumn('note');
        });
    }
};
