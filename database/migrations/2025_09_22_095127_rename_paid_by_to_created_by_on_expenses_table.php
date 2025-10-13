<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
// php artisan make:migration rename_paid_by_to_created_by_on_expenses_table
public function up(): void {
    Schema::table('expenses', function (Blueprint $t) {
        if (Schema::hasColumn('expenses', 'paid_by') && !Schema::hasColumn('expenses', 'created_by')) {
            $t->renameColumn('paid_by', 'created_by');
        }
    });
}
public function down(): void {
    Schema::table('expenses', function (Blueprint $t) {
        if (Schema::hasColumn('expenses', 'created_by') && !Schema::hasColumn('expenses', 'paid_by')) {
            $t->renameColumn('created_by', 'paid_by');
        }
    });
}

};
