<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique();
            $table->date('dob')->nullable();
            $table->string('avatar_path')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone','dob','avatar_path']);
        });
    }
};
