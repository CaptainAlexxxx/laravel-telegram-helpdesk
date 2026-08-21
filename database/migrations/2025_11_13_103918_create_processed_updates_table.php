<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_updates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('update_id')->unique()->comment('Telegram update_id');
            $table->timestamp('processed_at')->useCurrent()->comment('When update was processed');

            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_updates');
    }
};
