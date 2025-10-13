<?php
// 2025_09_16_090000_add_notifications_polls_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Notifications + Polls theo tài liệu
    |--------------------------------------------------------------------------
    | - Notifications: due_reminder/payment_verified/new_expense
    | - Polls/Poll Votes: bỏ phiếu khoản chi lớn (ngưỡng % thông qua)
    */

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Notifications (Thông báo)
        |--------------------------------------------------------------------------
        */
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $t->enum('type', ['due_reminder','payment_verified','new_expense']);
            $t->string('title', 150);
            $t->text('body')->nullable();
            $t->boolean('is_read')->default(false)->index();
            $t->timestamp('sent_at')->useCurrent();
            $t->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Polls (Cuộc bỏ phiếu cho khoản chi)
        |--------------------------------------------------------------------------
        */
        Schema::create('polls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $t->string('question', 255);
            $t->unsignedInteger('threshold')->default(60); // 0..100
            $t->timestamp('expires_at')->nullable();
            $t->enum('status', ['open','closed'])->default('open')->index();
            $t->timestamps();
        });

        Schema::create('poll_votes', function (Blueprint $t) {
            $t->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('choice', ['agree','disagree','abstain']);
            $t->timestamp('voted_at')->useCurrent();
            $t->primary(['poll_id','user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('notifications');
    }
};
