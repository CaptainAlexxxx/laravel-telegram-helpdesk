<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('telegram_users')->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('cascade');
            $table->enum('event_type', ['message', 'callback', 'command', 'status_change', 'rating', 'ticket'])->comment('Event type');
            $table->enum('event_source', ['telegram_user', 'telegram_agent', 'system_task', 'web'])->comment('Event source');
            $table->json('payload')->nullable()->comment('Additional event data');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
