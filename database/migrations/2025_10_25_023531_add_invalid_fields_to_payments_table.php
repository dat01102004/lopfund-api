<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->timestamp('invalidated_at')->nullable()->after('verified_at');
            $t->foreignId('invalidated_by')->nullable()->after('invalidated_at')->constrained('users');
            $t->string('invalid_reason', 120)->nullable()->after('invalidated_by');
            $t->text('invalid_note')->nullable()->after('invalid_reason');
        });

        // CHỈ cần nếu cột status đang là ENUM.
        // Nếu status là VARCHAR/string thì bỏ đoạn dưới.
        DB::statement(
          "ALTER TABLE payments
           MODIFY COLUMN status
           ENUM('submitted','verified','rejected','invalid')
           NOT NULL DEFAULT 'submitted'"
        );
    }

    public function down(): void
    {
        // Hoàn tác ENUM nếu bạn đã sửa ở up()
        DB::statement(
          "ALTER TABLE payments
           MODIFY COLUMN status
           ENUM('submitted','verified','rejected')
           NOT NULL DEFAULT 'submitted'"
        );

        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invalidated_by');
            $t->dropColumn(['invalidated_at','invalid_reason','invalid_note']);
        });
    }
};
