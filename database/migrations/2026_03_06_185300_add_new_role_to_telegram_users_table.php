<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table
                ->enum('role', ['client', 'agent', 'admin', 'employee'])
                ->change();
        });
    }

    public function down()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->enum('role', ['client', 'agent', 'admin'])
                ->change();
        });
    }
};
