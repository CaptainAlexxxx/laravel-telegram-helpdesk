<?php

namespace App\Telegram\Handlers;

use App\Models\BotLog;
use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Services\AgentGuard;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\TicketService;
use App\Services\WorkScheduleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class AgentMediaGroupHandler
{
    const ALBUM_TIMEOUT = 2; // seconds to wait for all album items

    /**
     * Handle agent media group (album from service chat)
     */
    public function __invoke(Nutgram $bot): void
    {
        $message = $bot->message();
        $chatId = $bot->chatId();
        $serviceChatId = config('bot.service_chat.chat_id');

        // Only process messages from service chat
        if ($chatId != $serviceChatId) {
            return;
        }

        // Only process media groups
        if (! isset($message->media_group_id)) {
            return;
        }

        // Must be in forum topic
        if (! isset($message->message_thread_id)) {
            return;
        }

        // Must be reply to bot message
        if (! isset($message->reply_to_message)) {
            Log::debug('AgentMediaGroupHandler: skipping non-reply album', [
                'media_group_id' => $message->media_group_id,
            ]);

            return;
        }

        $replyToMessage = $message->reply_to_message;
        $botId = config('nutgram.bot_id');

        // Check if reply is to BOT message
        if ($replyToMessage->from->id != $botId) {
            Log::debug('AgentMediaGroupHandler: skipping agent-to-agent album', [
                'reply_to_user_id' => $replyToMessage->from->id,
            ]);

            return;
        }

        // Ignore replies to service messages
        if ($this->isServiceMessage($replyToMessage)) {
            Log::debug('AgentMediaGroupHandler: skipping reply to service message');

            return;
        }

        $mediaGroupId = $message->media_group_id;
        $forumTopicId = $message->message_thread_id;
        $cacheKey = "agent_media_group:{$forumTopicId}:{$mediaGroupId}";
        $lockKey = "agent_media_group_lock:{$forumTopicId}:{$mediaGroupId}";

        // Get or create album cache
        $album = Cache::get($cacheKey, [
            'media_group_id' => $mediaGroupId,
            'forum_topic_id' => $forumTopicId,
            'service_chat_id' => $serviceChatId,
            'agent_id' => $bot->userId(),
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

        Log::debug('Agent media group item received', [
            'media_group_id' => $mediaGroupId,
            'current_count' => count($album['messages']),
            'message_id' => $message->message_id,
            'forum_topic_id' => $forumTopicId,
        ]);

        // Use lock to prevent multiple jobs for same album
        $hasLock = Cache::add($lockKey, true, now()->addSeconds(self::ALBUM_TIMEOUT + 10));

        if ($hasLock) {
            // First photo or lock acquired - schedule processing
            Log::debug('Scheduling agent album processing', [
                'media_group_id' => $mediaGroupId,
                'timeout' => self::ALBUM_TIMEOUT,
            ]);

            dispatch(function () use ($cacheKey, $forumTopicId, $mediaGroupId, $serviceChatId, $lockKey) {
                sleep(self::ALBUM_TIMEOUT);
                $this->processAgentAlbum($cacheKey, $forumTopicId, $mediaGroupId, $serviceChatId);

                // Release lock
                Cache::forget($lockKey);
            })->afterResponse();
        } else {
            Log::debug('Agent album processing already scheduled', [
                'media_group_id' => $mediaGroupId,
                'current_count' => count($album['messages']),
            ]);
        }
    }

    /**
     * Check if message is a service message
     */
    protected function isServiceMessage($message): bool
    {
        return isset($message->forum_topic_created) ||
            isset($message->forum_topic_edited) ||
            isset($message->forum_topic_closed) ||
            isset($message->forum_topic_reopened) ||
            isset($message->pinned_message);
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
     * Process complete agent album and forward to client
     */
    protected function processAgentAlbum(string $cacheKey, int $forumTopicId, string $mediaGroupId, int $serviceChatId): void
    {
        $album = Cache::get($cacheKey);

        if (! $album || empty($album['messages'])) {
            Log::warning('Agent album not found in cache or empty', [
                'cache_key' => $cacheKey,
                'media_group_id' => $mediaGroupId,
            ]);

            return;
        }

        Cache::forget($cacheKey);

        Log::info('Processing agent media album', [
            'media_group_id' => $mediaGroupId,
            'items_count' => count($album['messages']),
            'forum_topic_id' => $forumTopicId,
        ]);

        // Find ticket by forum topic
        $ticket = app(TicketService::class)->getTicketByForumTopic($forumTopicId, $serviceChatId);

        if (! $ticket) {
            Log::warning('Ticket not found for agent album', [
                'forum_topic_id' => $forumTopicId,
                'service_chat_id' => $serviceChatId,
            ]);

            return;
        }

        // Get agent
        $agent = TelegramUser::where('telegram_id', $album['agent_id'])->first();

        if (! $agent) {
            Log::warning('Agent not found for album', [
                'agent_id' => $album['agent_id'],
            ]);

            return;
        }

        // Check agent permissions
        $agentGuard = app(AgentGuard::class);
        if (! $agentGuard->canPerformAgentAction($agent)) {
            Log::warning('Agent not authorized to send album', [
                'agent_id' => $agent->id,
            ]);

            return;
        }

        // Update ticket metrics
        $ticketService = app(TicketService::class);
        if (! $ticket->first_reply_at) {
            $ticketService->addFirstReply($ticket, $agent->id);
        } else {
            $ticketService->updateLastAgent($ticket, $agent->id);
        }

        // Reset agent waiting timer
        $telegramService = app(TelegramService::class);
        $telegramService->resetAgentWaitingTimer($ticket);

        // Forward album to client
        $client = $ticket->user;

        foreach ($album['messages'] as $item) {
            $result = $telegramService->copyMessageToClient(
                $client->telegram_id,
                $item['chat_id'],
                $item['message_id']
            );

            if ($result) {
                // Mark original message in service chat as forwarded to client
                $telegramService->addForwardedToClientReaction(
                    $item['chat_id'],
                    $item['message_id']
                );
            } else {
                Log::error('Failed to forward agent album item to client', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $item['message_id'],
                ]);
            }

            // Small delay to preserve order
            usleep(50000); // 50ms
        }

        Log::info('Agent album forwarded to client', [
            'ticket_id' => $ticket->id,
            'client_id' => $client->telegram_id,
            'items_forwarded' => count($album['messages']),
        ]);

        // Check if agent replied during non-working hours — ask about reminder
        $scheduleService = app(WorkScheduleService::class);
        if (! $scheduleService->isWorkingHoursNow() && $client) {
            $afterHoursRecord = $scheduleService->handleAgentReplyAfterHours($client);

            if ($afterHoursRecord) {
                $templateService = app(TemplateService::class);
                $promptText = $templateService->getTemplate('messages.after_hours_agent_prompt', 1)
                    ?? "\xF0\x9F\x92\xAC Client was already replied to during non-working hours. Remind again at work hours?";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Yes, remind', 'callback_data' => "afterhours_remind:{$afterHoursRecord->id}:yes"],
                            ['text' => '❌ No', 'callback_data' => "afterhours_remind:{$afterHoursRecord->id}:no"],
                        ],
                    ],
                ];

                $telegramService->sendMessageToTopic(
                    $ticket->service_chat_id,
                    $ticket->forum_topic_id,
                    $promptText,
                    $keyboard
                );
            }
        }

        // Log agent album
        BotLog::logEvent(
            eventType: 'message',
            eventSource: 'telegram_agent',
            userId: $ticket->user_id,
            ticketId: $ticket->id,
            payload: [
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name,
                'text' => '[Media album]',
                'album_size' => count($album['messages']),
            ]
        );
    }
}
