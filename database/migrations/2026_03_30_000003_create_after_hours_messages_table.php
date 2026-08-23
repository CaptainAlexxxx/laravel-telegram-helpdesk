<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('after_hours_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('set null');
            $table->dateTime('period_start')->comment('Start of the non-working period');
            $table->boolean('auto_reply_sent')->default(false)->comment('Whether auto-reply was sent to client');
            $table->boolean('reminder_needed')->default(true)->comment('Whether reminder should be sent at work hours');
            $table->boolean('reminder_sent')->default(false)->comment('Whether reminder was already sent');
            $table->timestamps();

            $table->index(['telegram_user_id', 'period_start']);
            $table->index(['reminder_needed', 'reminder_sent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('after_hours_messages');
    }
};
