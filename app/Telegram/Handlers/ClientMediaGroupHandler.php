<?php

namespace App\Telegram\Handlers;

use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Services\TelegramService;
use App\Services\TicketService;
use App\Services\WorkScheduleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class ClientMediaGroupHandler
{
    const ALBUM_TIMEOUT = 3; // seconds to wait after LAST photo

    /**
     * Handle media group (album)
     */
    public function __invoke(Nutgram $bot): void
    {
        $message = $bot->message();

        if (! isset($message->media_group_id)) {
            return; // Not a media group - continue processing
        }

        // Only process messages from PRIVATE chats (clients)
        $serviceChatId = config('bot.service_chat.chat_id');
        if ($bot->chatId() == $serviceChatId) {
            return; // Agent albums handled by AgentMediaGroupHandler
        }

        $mediaGroupId = $message->media_group_id;
        $userId = $bot->userId();
        $cacheKey = "media_group:{$userId}:{$mediaGroupId}";
        $lockKey = "media_group_lock:{$userId}:{$mediaGroupId}";

        // Get or create album cache
        $album = Cache::get($cacheKey, [
            'media_group_id' => $mediaGroupId,
            'user_id' => $userId,
            'messages' => [],
            'created_at' => now(),
        ]);

        // Add current message to album
        $album['messages'][] = [
            'message_id' => $message->message_id,
            'chat_id' => $message->chat->id,
            'type' => $this->getMediaType($message),
            'caption' => $message->caption ?? null,
            'file_id' => $this->getFileId($message),
        ];

        $album['last_update'] = now();

        // Store album with LONGER timeout to handle slow webhooks
        Cache::put($cacheKey, $album, now()->addSeconds(self::ALBUM_TIMEOUT + 5));

        Log::debug('Client media group item received', [
            'media_group_id' => $mediaGroupId,
            'current_count' => count($album['messages']),
            'message_id' => $message->message_id,
            'user_id' => $userId,
        ]);

        // Use lock to prevent multiple jobs for same album
        $hasLock = Cache::add($lockKey, true, now()->addSeconds(self::ALBUM_TIMEOUT + 10));

        if ($hasLock) {
            // First photo or lock acquired - schedule processing
            Log::debug('Scheduling album processing', [
                'media_group_id' => $mediaGroupId,
                'timeout' => self::ALBUM_TIMEOUT,
            ]);

            dispatch(function () use ($cacheKey, $userId, $mediaGroupId, $lockKey) {
                // Wait for all photos to arrive
                sleep(self::ALBUM_TIMEOUT);

                // Process album
                $this->processAlbum($cacheKey, $userId, $mediaGroupId);

                // Release lock
                Cache::forget($lockKey);
            })->afterResponse();
        } else {
            Log::debug('Album processing already scheduled', [
                'media_group_id' => $mediaGroupId,
                'current_count' => count($album['messages']),
            ]);
        }
    }

    /**
     * Get media type from message
     */
    protected function getMediaType($message): string
    {
        if (! empty($message->photo)) {
            return 'photo';
        }
        if (! empty($message->video)) {
            return 'video';
        }
        if (! empty($message->document)) {
            return 'document';
        }

        return 'unknown';
    }

    /**
     * Get file ID from message
     */
    protected function getFileId($message): ?string
    {
        if (! empty($message->photo)) {
            return end($message->photo)->file_id;
        }
        if (! empty($message->video)) {
            return $message->video->file_id;
        }
        if (! empty($message->document)) {
            return $message->document->file_id;
        }

        return null;
    }

    /**
     * Process complete album and forward to service chat
     */
    protected function processAlbum(string $cacheKey, int $userId, string $mediaGroupId): void
    {
        $album = Cache::get($cacheKey);

        if (! $album || empty($album['messages'])) {
            Log::warning('Client album not found in cache or empty', [
                'cache_key' => $cacheKey,
                'media_group_id' => $mediaGroupId,
            ]);

            return;
        }

        Cache::forget($cacheKey);

        Log::info('Processing client media album', [
            'media_group_id' => $mediaGroupId,
            'items_count' => count($album['messages']),
            'user_id' => $userId,
        ]);

        // Get user
        $user = TelegramUser::where('telegram_id', $userId)->first();
        if (! $user) {
            Log::warning('User not found for album', ['user_id' => $userId]);

            return;
        }

        $ticketService = app(TicketService::class);
        $telegramService = app(TelegramService::class);
        $ticket = $ticketService->getUserActiveTicket($user);

        if (! $ticket) {
            $firstMessage = $album['messages'][0];
            $subject = ! empty($firstMessage['caption'])
                ? $firstMessage['caption']
                : '[Media without caption]';

            // Create new ticket
            $ticket = $ticketService->createTicket($user, $subject);

            // Update last client message time
            $ticket->update(['last_client_message_at' => now()]);

            // Create or reuse forum topic
            $this->createOrReuseForumTopic($ticket, $user, $telegramService);

            $ticket->refresh();

            // Reminder needs the forum topic already attached
            $telegramService->scheduleReminderJob($ticket);
        }

        if (! $ticket->forum_topic_id) {
            Log::error('Ticket has no forum topic after creation, cannot forward album', [
                'ticket_id' => $ticket->id,
                'forum_topic_id' => $ticket->forum_topic_id,
                'service_chat_id' => $ticket->service_chat_id,
                'user_id' => $userId,
            ]);

            return;
        }

        // Forward album to service chat
        $successCount = 0;
        foreach ($album['messages'] as $index => $item) {
            $result = $telegramService->copyMessageToTopic(
                $ticket->service_chat_id,
                $item['chat_id'],
                $item['message_id'],
                $ticket->forum_topic_id
            );

            if ($result) {
                $successCount++;
            } else {
                Log::error('Failed to forward client album item', [
                    'ticket_id' => $ticket->id,
                    'item_index' => $index,
                    'message_id' => $item['message_id'],
                ]);
            }

            // Small delay to preserve order
            usleep(50000); // 50ms
        }

        Log::info('Client album forwarded to service chat', [
            'ticket_id' => $ticket->id,
            'items_total' => count($album['messages']),
            'items_forwarded' => $successCount,
        ]);

        // Check after-hours and send auto-reply if needed
        $this->handleAfterHoursAutoReply($user, $ticket);
    }

    /**
     * Handle after-hours auto-reply for album messages
     */
    protected function handleAfterHoursAutoReply(TelegramUser $user, Ticket $ticket): void
    {
        $scheduleService = app(WorkScheduleService::class);

        if ($scheduleService->isWorkingHoursNow()) {
            return;
        }

        $scheduleService->recordAfterHoursMessage($user, $ticket, false);

        if (! $scheduleService->shouldSendAutoReply($user)) {
            return;
        }

        $autoReplyText = $scheduleService->getAutoReplyText($user);

        if (! $autoReplyText) {
            return;
        }

        $telegramService = app(TelegramService::class);
        $telegramService->sendMessageToUser($user->telegram_id, $autoReplyText);

        $scheduleService->recordAfterHoursMessage($user, $ticket, true);

        Log::info('After-hours auto-reply sent for album', [
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Create or reuse forum topic for ticket
     */
    protected function createOrReuseForumTopic(Ticket $ticket, TelegramUser $user, TelegramService $telegramService): void
    {
        $serviceChatId = config('nutgram.service_chat_id');

        if (! $serviceChatId) {
            Log::warning('Service chat not configured', ['ticket_id' => $ticket->id]);

            return;
        }

        $ticketService = app(TicketService::class);

        // Check if user already has a permanent forum topic
        if ($user->forum_topic_id && $user->forum_topic_chat_id == $serviceChatId) {
            // Reuse existing topic
            $ticketService->updateForumTopic($ticket, $user->forum_topic_id, $serviceChatId);
            $this->sendNewTicketMessageToTopic($ticket, $user, $telegramService, $serviceChatId, $user->forum_topic_id);

            return;
        }

        // Create NEW permanent topic
        $topicName = "#{$user->telegram_id} ".
            ($user->username ? "@{$user->username}" : '').
            " {$user->full_name}";

        $result = $telegramService->createForumTopic($serviceChatId, $topicName);

        if (! $result || ! isset($result['message_thread_id'])) {
            Log::error('Failed to create forum topic', ['ticket_id' => $ticket->id]);

            return;
        }

        $forumTopicId = $result['message_thread_id'];

        // Save to user
        $user->update([
            'forum_topic_id' => $forumTopicId,
            'forum_topic_chat_id' => $serviceChatId,
        ]);

        // Update ticket
        $ticketService->updateForumTopic($ticket, $forumTopicId, $serviceChatId);

        // Send new ticket message
        $this->sendNewTicketMessageToTopic($ticket, $user, $telegramService, $serviceChatId, $forumTopicId);
    }

    /**
     * Send new ticket message and pin it
     */
    protected function sendNewTicketMessageToTopic(Ticket $ticket, TelegramUser $user, TelegramService $telegramService, int $serviceChatId, int $forumTopicId): void
    {
        $initialMessage = "🎫 <b>New Ticket #{$ticket->id}</b>\n\n".
            "👤 User: {$user->full_name}\n".
            "🆔 Telegram ID: {$user->telegram_id}\n".
            ($user->username ? "📱 Username: @{$user->username}\n" : '').
            "\n📝 <b>Issue:</b>\n{$ticket->subject}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔒 Close Ticket', 'callback_data' => "agent_close:{$ticket->id}"],
                ],
            ],
        ];

        $result = $telegramService->sendMessageToTopic(
            $serviceChatId,
            $forumTopicId,
            $initialMessage,
            $keyboard
        );

        // Pin and save message_id (but don't fail if pinning fails)
        if ($result && isset($result['message_id'])) {
            $messageId = $result['message_id'];
            $ticket->update(['pinned_message_id' => $messageId]);

            // Try to pin (but don't fail the entire process)
            try {
                $pinSuccess = $telegramService->pinChatMessage($serviceChatId, $messageId, $forumTopicId);

                if ($pinSuccess) {
                    Log::info('New ticket message pinned for album', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $messageId,
                    ]);
                } else {
                    Log::warning('Failed to pin new ticket message for album (but continuing)', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $messageId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception while pinning ticket message for album (but continuing)', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
