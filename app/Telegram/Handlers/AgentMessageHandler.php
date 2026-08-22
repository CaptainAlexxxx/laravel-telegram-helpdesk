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
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class AgentMessageHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $message = $bot->message();
        $chatId = $bot->chatId();
        $serviceChatId = config('bot.service_chat.chat_id');

        // Check if message is from service chat
        if ($chatId != $serviceChatId) {
            return;
        }

        // Skip media groups - they are handled by AgentMediaGroupHandler
        if (isset($message->media_group_id)) {
            Log::debug('AgentMessageHandler: skipping media group (handled by AgentMediaGroupHandler)', [
                'media_group_id' => $message->media_group_id,
            ]);

            return;
        }

        // Check if message is in a forum topic
        if (! isset($message->message_thread_id)) {
            return;
        }

        // Only process replies to BOT messages with actual content
        if (! isset($message->reply_to_message)) {
            Log::debug('AgentMessageHandler: skipping non-reply message', [
                'message_id' => $message->message_id,
                'forum_topic_id' => $message->message_thread_id,
            ]);

            return;
        }

        $replyToMessage = $message->reply_to_message;
        $botId = config('nutgram.bot_id');

        // Check if reply is to BOT message
        if ($replyToMessage->from->id != $botId) {
            Log::debug('AgentMessageHandler: skipping agent-to-agent reply', [
                'reply_to_user_id' => $replyToMessage->from->id,
                'bot_id' => $botId,
            ]);

            return;
        }

        // Ignore replies to service messages (forum_topic_created, etc)
        if ($this->isServiceMessage($replyToMessage)) {
            Log::debug('AgentMessageHandler: skipping reply to service message', [
                'reply_to_message_id' => $replyToMessage->message_id,
                'has_forum_topic_created' => isset($replyToMessage->forum_topic_created),
                'has_forum_topic_edited' => isset($replyToMessage->forum_topic_edited),
                'has_forum_topic_closed' => isset($replyToMessage->forum_topic_closed),
            ]);

            return;
        }

        $forumTopicId = $message->message_thread_id;

        // Find ticket by forum topic ID
        $ticket = app(TicketService::class)->getTicketByForumTopic($forumTopicId, $serviceChatId);

        if (! $ticket) {
            Log::warning('AgentMessageHandler: ticket not found for forum topic', [
                'forum_topic_id' => $forumTopicId,
                'service_chat_id' => $serviceChatId,
            ]);

            return;
        }

        // Get or create agent user
        $agentTelegramId = $bot->userId();
        $agent = TelegramUser::findOrCreateByTelegramId($agentTelegramId, [
            'username' => $bot->user()->username,
            'first_name' => $bot->user()->first_name,
            'last_name' => $bot->user()->last_name,
            'role' => 'client', // Will be promoted if allowed
        ]);

        // Check agent permissions
        $agentGuard = app(AgentGuard::class);

        // Try to promote to agent if allowed
        if ($agent->role === 'client') {
            if (! $agentGuard->promoteToAgentIfAllowed($agent)) {
                Log::warning('User not allowed to be agent', [
                    'telegram_id' => $agentTelegramId,
                    'username' => $agent->username,
                ]);

                $bot->sendMessage(
                    text: '<b>Access Denied:</b> You are not authorized to respond to tickets.',
                    chat_id: $chatId,
                    message_thread_id: $forumTopicId,
                    parse_mode: 'HTML'
                );

                return;
            }
        }

        // Verify agent can perform action
        if (! $agentGuard->canPerformAgentAction($agent)) {
            $bot->sendMessage(
                text: '<b>Access Denied:</b> You are not authorized to respond to tickets.',
                chat_id: $chatId,
                message_thread_id: $forumTopicId,
                parse_mode: 'HTML'
            );

            return;
        }

        $ticketService = app(TicketService::class);

        // Set first agent reply if not set
        if (! $ticket->first_reply_at) {
            $ticketService->addFirstReply($ticket, $agent->id);
        } else {
            $ticketService->updateLastAgent($ticket, $agent->id);
        }

        // Reset agent waiting timer and schedule auto-close
        $telegramService = app(TelegramService::class);
        $telegramService->resetAgentWaitingTimer($ticket);

        // Forward message to client
        $this->forwardMessageToClient($bot, $ticket, $message);

        // Check if agent replied during non-working hours — ask about reminder
        $this->handleAgentAfterHoursReply($bot, $ticket);

        // Log agent message
        BotLog::logEvent(
            eventType: 'message',
            eventSource: 'telegram_agent',
            userId: $ticket->user_id,
            ticketId: $ticket->id,
            payload: [
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name,
                'text' => $this->getMessageText($message),
                'has_photo' => ! empty($message->photo),
                'has_document' => ! empty($message->document),
                'reply_to_message_id' => $message->reply_to_message->message_id,
            ]
        );

        Log::info('Agent message processed and sent to client', [
            'ticket_id' => $ticket->id,
            'agent_id' => $agent->id,
            'client_id' => $ticket->user_id,
        ]);
    }

    /**
     * Check if message is a service message (not client content)
     */
    protected function isServiceMessage($message): bool
    {
        // Service message indicators
        return isset($message->forum_topic_created) ||
            isset($message->forum_topic_edited) ||
            isset($message->forum_topic_closed) ||
            isset($message->forum_topic_reopened) ||
            isset($message->pinned_message) ||
            isset($message->new_chat_members) ||
            isset($message->left_chat_member) ||
            isset($message->new_chat_title) ||
            isset($message->new_chat_photo) ||
            isset($message->delete_chat_photo) ||
            isset($message->group_chat_created) ||
            isset($message->supergroup_chat_created) ||
            isset($message->channel_chat_created);
    }

    /**
     * Forward message from agent to client
     */
    protected function forwardMessageToClient(Nutgram $bot, Ticket $ticket, $message): void
    {
        $client = $ticket->user;
        $telegramService = app(TelegramService::class);

        try {
            // Copy message to preserve formatting and media
            $telegramService->copyMessageToClient(
                $client->telegram_id,
                $message->chat->id,
                $message->message_id
            );

            // Mark original message in service chat as forwarded to client
            $telegramService->addForwardedToClientReaction(
                $message->chat->id,
                $message->message_id
            );
        } catch (\Exception $e) {
            Log::error('Failed to forward agent message to client', [
                'ticket_id' => $ticket->id,
                'client_id' => $client->telegram_id,
                'error' => $e->getMessage(),
            ]);

            // Fallback: send as text
            if (! empty($message->text)) {
                try {
                    $bot->sendMessage(
                        chat_id: $client->telegram_id,
                        text: $message->text,
                        parse_mode: 'HTML'
                    );
                } catch (\Exception $e2) {
                    Log::error('Failed to send fallback message to client', [
                        'ticket_id' => $ticket->id,
                        'error' => $e2->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * If agent replies during non-working hours, ask whether to keep reminder
     */
    protected function handleAgentAfterHoursReply(Nutgram $bot, Ticket $ticket): void
    {
        $scheduleService = app(WorkScheduleService::class);

        if ($scheduleService->isWorkingHoursNow()) {
            return;
        }

        $client = $ticket->user;

        if (! $client) {
            return;
        }

        $afterHoursRecord = $scheduleService->handleAgentReplyAfterHours($client);

        if (! $afterHoursRecord) {
            return;
        }

        // Send prompt to agent in topic
        $templateService = app(TemplateService::class);
        $telegramService = app(TelegramService::class);

        $promptText = $templateService->getTemplate('messages.after_hours_agent_prompt', 1)
            ?? '💬 Client was already replied to during non-working hours. Remind again at work hours?';

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

        Log::info('After-hours agent prompt sent', [
            'ticket_id' => $ticket->id,
            'after_hours_id' => $afterHoursRecord->id,
        ]);
    }

    /**
     * Get message text with support for different message types
     */
    protected function getMessageText($message): string
    {
        if (! empty($message->caption)) {
            return $message->caption;
        }

        if (! empty($message->text)) {
            return $message->text;
        }

        if (! empty($message->photo)) {
            return '[Photo]';
        }

        if (! empty($message->document)) {
            return '[Document]';
        }

        return '[Media message]';
    }
}
