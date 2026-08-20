<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reminder_job_id')->nullable()->after('service_chat_id')->comment('Reminder job ID');
            $table->string('auto_close_job_id')->nullable()->after('reminder_job_id')->comment('Auto-close job ID');
            $table->timestamp('last_client_message_at')->nullable()->after('auto_close_job_id')->comment('Last client message time');
            $table->timestamp('last_agent_message_at')->nullable()->after('last_client_message_at')->comment('Last agent message time');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_job_id',
                'auto_close_job_id',
                'last_client_message_at',
                'last_agent_message_at',
            ]);
        });
    }
};
