<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->string('state')->comment('Current conversation state');
            $table->json('data')->nullable()->comment('Additional state data');
            $table->timestamp('expires_at')->nullable()->comment('State expiration time');
            $table->timestamps();

            $table->index(['user_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
