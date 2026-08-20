<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->bigInteger('forum_topic_id')->nullable()->after('locale_id')->comment('Permanent forum topic ID for all user tickets');
            $table->bigInteger('forum_topic_chat_id')->nullable()->after('forum_topic_id')->comment('Service chat ID where topic exists');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['forum_topic_id', 'forum_topic_chat_id']);
        });
    }
};
