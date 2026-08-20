<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_resources', function (Blueprint $table) {
            $table->enum('type', ['button', 'message', 'alert', 'label'])
                ->comment('Resource type')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('bot_resources', function (Blueprint $table) {
            $table->enum('type', ['button', 'message', 'alert'])
                ->comment('Resource type')
                ->change();
        });
    }
};
