<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_locales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Language code (uk, en, pl, etc.)');
            $table->string('name', 100)->comment('Language name');
            $table->boolean('is_default')->default(false)->comment('Default language flag');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_locales');
    }
};
