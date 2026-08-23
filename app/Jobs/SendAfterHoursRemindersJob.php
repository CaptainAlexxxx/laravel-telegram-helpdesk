<?php

namespace App\Jobs;

use App\Models\AfterHoursMessage;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\WorkScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAfterHoursRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(
        WorkScheduleService $scheduleService,
        TelegramService $telegramService,
        TemplateService $templateService
    ): void {
        // Only run during working hours
        if (! $scheduleService->isWorkingHoursNow()) {
            return;
        }

        $pendingReminders = $scheduleService->getPendingReminders();

        if ($pendingReminders->isEmpty()) {
            return;
        }

        // Group by user (one reminder per client)
        $grouped = $pendingReminders->groupBy('telegram_user_id');

        foreach ($grouped as $userId => $records) {
            /** @var AfterHoursMessage $record */
            $record = $records->first();
            $user = $record->telegramUser;
            $ticket = $record->ticket;

            // Skip if ticket closed or agent was the last to reply
            if ($ticket && ($ticket->isClosed() ||
                ($ticket->last_agent_message_at &&
                    (! $ticket->last_client_message_at ||
                        $ticket->last_agent_message_at >= $ticket->last_client_message_at)))) {
                foreach ($records as $r) {
                    $scheduleService->markReminderSent($r);
                }

                continue;
            }

            if (! $user) {
                Log::warning('AfterHoursReminder: user not found', ['user_id' => $userId]);
                foreach ($records as $r) {
                    $scheduleService->markReminderSent($r);
                }

                continue;
            }

            // Find the forum topic to send reminder to
            $forumTopicId = $user->forum_topic_id;
            $serviceChatId = $user->forum_topic_chat_id;

            if (! $forumTopicId || ! $serviceChatId) {
                if ($ticket && $ticket->forum_topic_id && $ticket->service_chat_id) {
                    $forumTopicId = $ticket->forum_topic_id;
                    $serviceChatId = $ticket->service_chat_id;
                } else {
                    Log::warning('AfterHoursReminder: no forum topic for user', [
                        'user_id' => $userId,
                        'telegram_id' => $user->telegram_id,
                    ]);
                    foreach ($records as $r) {
                        $scheduleService->markReminderSent($r);
                    }

                    continue;
                }
            }

            // Build reminder text
            $reminderBody = $templateService->getTemplate('messages.after_hours_reminder', 1)
                ?? 'Client wrote during non-working hours, please check.';

            $reminderText = "⏰ <b>After-hours message</b>\n\n".
                "👤 Client: {$user->full_name}\n".
                ($user->username ? "📱 @{$user->username}\n" : '').
                "\n{$reminderBody}";

            $telegramService->sendMessageToTopic(
                $serviceChatId,
                $forumTopicId,
                $reminderText
            );

            // Mark all records for this user as sent
            foreach ($records as $r) {
                $scheduleService->markReminderSent($r);
            }

            Log::info('After-hours reminder sent', [
                'user_id' => $userId,
                'telegram_id' => $user->telegram_id,
                'records_count' => $records->count(),
            ]);
        }
    }
}
