<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Nếu còn cột 'name' (di sản), xử lý để không chặn insert
        if (Schema::hasColumn('fund_accounts', 'name')) {
            if (!Schema::hasColumn('fund_accounts', 'account_name')) {
                // Trường hợp chưa có 'account_name' -> đổi tên 'name' thành 'account_name'
                DB::statement("ALTER TABLE fund_accounts CHANGE COLUMN `name` `account_name` VARCHAR(120) NULL");
            } else {
                // Đã có 'account_name' rồi -> cho 'name' nullable hoặc xoá hẳn
                // Chọn 1 trong 2 dòng dưới (mình khuyên DROP cho sạch):
                DB::statement("ALTER TABLE fund_accounts DROP COLUMN `name`");
                // hoặc: DB::statement("ALTER TABLE fund_accounts MODIFY COLUMN `name` VARCHAR(120) NULL");
            }
        }
    }

    public function down(): void
    {
        // Không nhất thiết rollback
    }
};

