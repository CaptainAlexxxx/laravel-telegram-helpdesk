<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->text('subject')->comment('First client message');
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->bigInteger('forum_topic_id')->nullable()->comment('Forum topic ID');
            $table->bigInteger('service_chat_id')->nullable()->comment('Service chat ID');

            // Metrics
            $table->timestamp('first_reply_at')->nullable()->comment('First agent reply time');
            $table->timestamp('closed_at')->nullable()->comment('Ticket close time');
            $table->foreignId('first_agent_id')->nullable()->constrained('telegram_users')->onDelete('set null');
            $table->foreignId('last_agent_id')->nullable()->constrained('telegram_users')->onDelete('set null');

            // Review
            $table->tinyInteger('review_rating')->nullable()->comment('Rating 1-5');
            $table->text('review_comment')->nullable()->comment('Client review comment');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
