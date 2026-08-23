<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('day_of_week')->unique()->comment('1=Monday, 7=Sunday (ISO-8601)');
            $table->string('start_time', 5)->default('09:00')->comment('Format HH:mm');
            $table->string('end_time', 5)->default('18:00')->comment('Format HH:mm');
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
