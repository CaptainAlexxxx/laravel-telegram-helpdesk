<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_resources', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['button', 'message', 'alert'])->comment('Resource type');
            $table->string('resource_key')->comment('Unique resource key');
            $table->text('resource_value')->comment('Resource template');
            $table->text('description')->nullable()->comment('Resource description');
            $table->foreignId('locale_id')->constrained('bot_locales')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['resource_key', 'locale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_resources');
    }
};
