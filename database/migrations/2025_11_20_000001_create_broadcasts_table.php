<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('telegram_users')->onDelete('cascade');
            $table->enum('role', ['all', 'client', 'employee', 'agent', 'admin'])->default('all')->comment('Target audience role');
            $table->text('message_text')->nullable()->comment('Text message content');
            $table->string('photo_file_id')->nullable()->comment('Telegram file_id for photo');
            $table->text('caption')->nullable()->comment('Caption for photo message');
            $table->timestamp('scheduled_at')->comment('When to send the broadcast');
            $table->enum('status', ['pending', 'sent', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
