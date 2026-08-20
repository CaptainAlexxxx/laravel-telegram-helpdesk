<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique()->comment('Telegram user ID');
            $table->string('username')->nullable()->comment('Telegram username');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('role', ['client', 'agent', 'admin'])->default('client');
            $table->foreignId('locale_id')->nullable()->constrained('bot_locales')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
