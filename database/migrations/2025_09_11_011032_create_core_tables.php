<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users: thêm role
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('member')->index();
        });

        // ====================== CLASSES & CLASS_MEMBERS ======================
        Schema::create('classes', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();   // mã mời vào lớp
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('bank_account')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('qr_path')->nullable();
            $t->timestamps();
        });

        Schema::create('class_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member');   // owner|treasurer|member
            $table->string('status')->default('active'); // active|left ...
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['class_id','user_id']);
            // 👇 FIX: FK phải trỏ về 'classes' (không phải 'classrooms')
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ====================== FUND ACCOUNTS ======================
        Schema::create('fund_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->string('name');
            $t->string('bank_name')->nullable();
            $t->string('account_no')->nullable();
            $t->string('account_holder')->nullable();
            $t->string('qr_image_path')->nullable();
            $t->timestamps();
        });

        // ====================== FEE CYCLES & INVOICES ======================
        Schema::create('fee_cycles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->string('name');
            $t->string('term')->nullable();
            $t->unsignedInteger('amount_per_member');
            $t->date('due_date')->nullable();
            $t->enum('status', ['draft','active','closed'])->default('draft')->index();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fee_cycle_id')->constrained('fee_cycles')->cascadeOnDelete();
            $t->foreignId('member_id')->constrained('class_members')->cascadeOnDelete();
            $t->unsignedInteger('amount');
            $t->enum('status', ['unpaid','submitted','verified','paid'])->default('unpaid')->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['fee_cycle_id','member_id']);
        });

        // ====================== PAYMENTS ======================
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('payer_id')->constrained('class_members')->cascadeOnDelete();
            $t->unsignedInteger('amount');
            $t->enum('method', ['bank','momo','zalopay','cash'])->default('bank')->index();
            $t->string('txn_ref')->nullable();
            $t->string('proof_path')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->enum('status', ['submitted','verified','rejected'])->default('submitted')->index();
            $t->timestamps();
        });

        // ====================== EXPENSE FLOW ======================
        Schema::create('expense_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->string('title');
            $t->text('reason')->nullable();
            $t->unsignedInteger('amount_est');
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending','approved','rejected','funded'])->default('pending')->index();
            $t->timestamps();
        });

        Schema::create('expense_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('expense_requests')->cascadeOnDelete();
            $t->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $t->enum('vote', ['approve','reject']);
            $t->timestamps();
            $t->unique(['request_id','voter_id']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $t->foreignId('request_id')->nullable()->constrained('expense_requests')->nullOnDelete();
            $t->string('title');
            $t->unsignedInteger('amount');
            $t->date('spent_at')->nullable();
            $t->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
            $t->string('receipt_path')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_approvals');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_cycles');
        Schema::dropIfExists('fund_accounts');
        Schema::dropIfExists('class_members');
        Schema::dropIfExists('classes');

        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'role')) {
                $t->dropColumn('role');
            }
        });
    }
};
