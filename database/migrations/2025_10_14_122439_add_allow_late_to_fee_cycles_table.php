<?php

// database/migrations/xxxx_add_allow_late_to_fee_cycles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('fee_cycles', function (Blueprint $table) {
            $table->boolean('allow_late')->default(true)->after('due_date');
        });
    }
    public function down(): void {
        Schema::table('fee_cycles', function (Blueprint $table) {
            $table->dropColumn('allow_late');
        });
    }
};
