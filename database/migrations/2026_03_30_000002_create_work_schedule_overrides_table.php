<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->comment('Specific date for override');
            $table->boolean('is_working_day')->default(false);
            $table->string('start_time', 5)->nullable()->comment('Format HH:mm, required if is_working_day=true');
            $table->string('end_time', 5)->nullable()->comment('Format HH:mm, required if is_working_day=true');
            $table->string('description')->nullable()->comment('E.g. Christmas, New Year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_overrides');
    }
};
