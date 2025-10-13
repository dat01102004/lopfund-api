<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Thêm cột nếu thiếu
        Schema::table('fund_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('fund_accounts', 'class_id')) {
                $table->unsignedBigInteger('class_id')->index()->after('id');
            }
            if (!Schema::hasColumn('fund_accounts', 'bank_code')) {
                $table->string('bank_code', 20)->nullable()->after('class_id');
            }
            if (!Schema::hasColumn('fund_accounts', 'account_number')) {
                $table->string('account_number', 50)->nullable()->after('bank_code');
            }
            if (!Schema::hasColumn('fund_accounts', 'account_name')) {
                $table->string('account_name', 120)->nullable()->after('account_number');
            }
            // nếu bảng cũ chưa có timestamps thì thêm
            if (!Schema::hasColumn('fund_accounts', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // Không cần rollback phức tạp; có thể bỏ trống
        // hoặc drop các cột vừa thêm nếu muốn.
        // Schema::table('fund_accounts', function (Blueprint $table) {
        //     $table->dropColumn(['bank_code','account_number','account_name','created_at','updated_at']);
        // });
    }
};
