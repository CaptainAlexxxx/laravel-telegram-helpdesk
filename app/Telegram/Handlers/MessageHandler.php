<?php

namespace App\Telegram\Handlers;

use App\Models\BotLog;
use App\Models\Project;
use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleOverride;
use App\Services\ConversationService;
use App\Services\NotificationService;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\TicketService;
use App\Services\WorkScheduleService;
use App\Telegram\Commands\ScheduleCommand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MessageHandler
{
    public function __invoke(Nutgram $bot): void
    {
        try {
            $message = $bot->message();

            // Skip media group messages - they are handled by ClientMediaGroupHandler
            if (isset($message->media_group_id)) {
                Log::debug('Message is part of media group, skipping MessageHandler', [
                    'media_group_id' => $message->media_group_id,
                ]);

                return;
            }

            // Only process messages from PRIVATE chats (clients)
            // Service chat messages should be handled by AgentMessageHandler only
            $serviceChatId = config('bot.service_chat.chat_id');

            if ($bot->chatId() == $serviceChatId) {
                Log::debug('MessageHandler: skipping service chat message', [
                    'chat_id' => $bot->chatId(),
                ]);

                return;
            }

            // Ignore commands
            if ($message->text && str_starts_with($message->text, '/')) {
                return;
            }

            // Ignore bot's own messages
            $botId = config('nutgram.bot_id');
            if ($bot->userId() == $botId) {
                return;
            }

            // Ignore service messages (forum_topic_created, etc.)
            if (isset($message->forum_topic_created) ||
                isset($message->forum_topic_edited) ||
                isset($message->forum_topic_closed) ||
                isset($message->pinned_message)) {
                return;
            }

            $telegramId = $bot->userId();

            // Find or create user
            $user = TelegramUser::findOrCreateByTelegramId($telegramId, [
                'username' => $bot->user()->username,
                'first_name' => $bot->user()->first_name,
                'last_name' => $bot->user()->last_name,
            ]);

            $conversationService = app(ConversationService::class);

            // Check if user is in a conversation state
            if ($conversationService->hasActiveState($user)) {
                $this->handleConversationState($bot, $user, $message, $conversationService);

                return;
            }

            // Normal message processing (ticket handling)
            $this->handleTicketMessage($bot, $user, $message);

        } catch (\Throwable $e) {
            Log::error('MessageHandler error', [
                'user_id' => $bot->userId(),
                'exception' => $e,
            ]);

            // Notify client about error
            $notificationService = app(NotificationService::class);
            $notificationService->notifyClientAboutError($bot->chatId());
        }
    }

    /**
     * Handle conversation state (e.g., awaiting review comment, broadcast flow)
     */
    protected function handleConversationState(Nutgram $bot, TelegramUser $user, $message, $conversationService): void
    {
        $state = $conversationService->getState($user);
        $stateData = $conversationService->getStateData($user);

        if ($state === 'awaiting_review_comment') {
            $this->handleReviewComment($bot, $user, $message, $stateData, $conversationService);

            return;
        }

        if ($state === 'awaiting_project_selection') {
            // User sent a text message instead of pressing a button — resend the keyboard
            $this->handleProjectSelectionMessage($bot, $user, $conversationService);

            return;
        }

        // Schedule management states (admin)
        if ($state === 'awaiting_schedule_time') {
            $this->handleScheduleTimeInput($bot, $user, $message, $stateData, $conversationService);

            return;
        }

        if ($state === 'awaiting_override_date') {
            $this->handleOverrideDateInput($bot, $user, $message, $stateData, $conversationService);

            return;
        }

        if ($state === 'awaiting_override_time') {
            $this->handleOverrideTimeInput($bot, $user, $message, $stateData, $conversationService);

            return;
        }

        // Broadcast creation flow
        if (str_starts_with($state, 'mmsg_')) {
            $this->handleMmsgState($bot, $user, $message, $state, $stateData, $conversationService);

            return;
        }

        // Clear unknown state
        $conversationService->clearState($user);
    }

    /**
     * Handle review comment input
     */
    protected function handleReviewComment(Nutgram $bot, TelegramUser $user, $message, ?array $stateData, $conversationService): void
    {
        $ticketId = $stateData['ticket_id'] ?? null;

        if (! $ticketId) {
            $conversationService->clearState($user);
            $bot->sendMessage(
                text: 'Error: ticket not found'
            );

            return;
        }

        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            $conversationService->clearState($user);
            $bot->sendMessage(
                text: 'Error: ticket not found'
            );

            return;
        }

        $comment = $message->text ?? '';

        if (empty($comment)) {
            $bot->sendMessage(
                text: 'Please enter a text comment'
            );

            return;
        }

        // Save comment
        $ticket->update(['review_comment' => $comment]);

        // Clear conversation state
        $conversationService->clearState($user);

        // Send confirmation
        $templateService = app(TemplateService::class);
        $confirmMessage = $templateService->getTemplate('messages.review_comment_saved', $user->locale_id)
            ?? 'Thank you! Your comment has been saved.';

        $bot->sendMessage(
            text: $confirmMessage,
            parse_mode: 'HTML'
        );

        // Log comment
        BotLog::logEvent(
            eventType: 'rating',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: $ticket->id,
            payload: [
                'action' => 'comment_added',
                'comment' => $comment,
            ]
        );
    }

    /**
     * Dispatch broadcast conversation states
     */
    protected function handleMmsgState(
        Nutgram $bot,
        TelegramUser $user,
        $message,
        string $state,
        ?array $stateData,
        $conversationService
    ): void {
        match ($state) {
            'mmsg_awaiting_message' => $this->handleMmsgAwaitingMessage($bot, $user, $message, $stateData, $conversationService),
            'mmsg_awaiting_date' => $this->handleMmsgAwaitingDate($bot, $user, $message, $stateData, $conversationService),
            'mmsg_awaiting_time' => $this->handleMmsgAwaitingTime($bot, $user, $message, $stateData, $conversationService),
            'mmsg_select_role', 'mmsg_preview' => $bot->sendMessage(
                text: app(TemplateService::class)->getTemplate('messages.mmsg_use_buttons', $user->locale_id)
                    ?? 'Use the buttons above to continue, or /mmsg to cancel.',
                parse_mode: 'HTML'
            ),
            default => $conversationService->clearState($user),
        };
    }

    /**
     * Step 2: receive text or photo for the broadcast message
     */
    protected function handleMmsgAwaitingMessage(
        Nutgram $bot,
        TelegramUser $user,
        $message,
        ?array $stateData,
        $conversationService
    ): void {
        $templateService = app(TemplateService::class);
        $locale = $user->locale_id;

        if (! empty($message->photo)) {
            // Photo with optional caption
            $photo = end($message->photo); // largest available size
            $stateData['photo_file_id'] = $photo->file_id;
            $stateData['caption'] = $message->caption ?? null;
            $stateData['message_text'] = null;
        } elseif (! empty($message->text)) {
            $stateData['message_text'] = $message->text;
            $stateData['photo_file_id'] = null;
            $stateData['caption'] = null;
        } else {
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.mmsg_invalid_message', $locale)
                    ?? 'Please send a text message or a photo.',
                parse_mode: 'HTML'
            );

            return;
        }

        // If scheduled_at already exists (edit flow), skip date/time and go straight to preview
        if (! empty($stateData['scheduled_at'])) {
            $conversationService->setState($user, 'mmsg_preview', $stateData, 60);
            $this->sendMmsgPreview($bot, $user, $stateData, $templateService);

            return;
        }

        $conversationService->setState($user, 'mmsg_awaiting_date', $stateData, 60);

        $bot->sendMessage(
            text: $templateService->getTemplate('messages.mmsg_awaiting_date', $locale)
                ?? 'Message received. Step 3/3. Enter broadcast date:',
            parse_mode: 'HTML'
        );
    }

    /**
     * Step 3: receive date
     */
    protected function handleMmsgAwaitingDate(
        Nutgram $bot,
        TelegramUser $user,
        $message,
        ?array $stateData,
        $conversationService
    ): void {
        $templateService = app(TemplateService::class);
        $locale = $user->locale_id;
        $input = trim($message->text ?? '');

        $date = match (mb_strtolower($input)) {
            'сьогодні', 'today', 'сегодня' => Carbon::today(),
            'завтра',   'tomorrow' => Carbon::tomorrow(),
            default => null,
        };

        if (! $date) {
            try {
                $date = Carbon::createFromFormat('d-m-Y', $input)->startOfDay();
            } catch (\Exception) {
                $date = null;
            }
        }

        if (! $date || $date->isBefore(Carbon::today())) {
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.mmsg_invalid_date', $locale)
                    ?? 'Invalid date or date is in the past.',
                parse_mode: 'HTML'
            );

            return;
        }

        $stateData['date'] = $date->format('Y-m-d');
        $conversationService->setState($user, 'mmsg_awaiting_time', $stateData, 60);

        $text = $templateService->getTemplateWithVars('messages.mmsg_date_accepted', [
            'date' => $date->format('d.m.Y'),
        ], $locale) ?? "Date: {$date->format('d.m.Y')}. Enter broadcast time:";

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML'
        );
    }

    /**
     * Step 4: receive time, show preview with confirm buttons
     */
    protected function handleMmsgAwaitingTime(
        Nutgram $bot,
        TelegramUser $user,
        $message,
        ?array $stateData,
        $conversationService
    ): void {
        $templateService = app(TemplateService::class);
        $locale = $user->locale_id;
        $input = trim($message->text ?? '');

        try {
            $time = Carbon::createFromFormat('H:i', $input);
        } catch (\Exception) {
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.mmsg_invalid_time', $locale)
                    ?? 'Invalid time format. Enter in format HH:MM.',
                parse_mode: 'HTML'
            );

            return;
        }

        $scheduledAt = Carbon::parse($stateData['date'].' '.$time->format('H:i'));

        if ($scheduledAt->isPast()) {
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.mmsg_time_past', $locale)
                    ?? 'The specified time is in the past. Enter a different time:',
                parse_mode: 'HTML'
            );

            return;
        }

        $stateData['scheduled_at'] = $scheduledAt->toDateTimeString();
        $conversationService->setState($user, 'mmsg_preview', $stateData, 60);

        $this->sendMmsgPreview($bot, $user, $stateData, $templateService);
    }

    /**
     * Build and send broadcast preview with action buttons
     */
    protected function sendMmsgPreview(Nutgram $bot, TelegramUser $user, array $stateData, TemplateService $templateService): void
    {
        $locale = $user->locale_id;
        $scheduledAt = Carbon::parse($stateData['scheduled_at']);
        $roleLabel = $templateService->getTemplate('role.'.($stateData['role'] ?? 'all'), $locale) ?? $stateData['role'] ?? 'all';
        $messageText = $stateData['message_text'] ?? $stateData['caption'] ?? "\xE2\x80\x94";
        $hasPhoto = ! empty($stateData['photo_file_id']);
        $typeLabel = $hasPhoto
            ? ($templateService->getTemplate('mmsg.type_photo', $locale) ?? 'Photo')
            : ($templateService->getTemplate('mmsg.type_text', $locale) ?? 'Text');

        $preview = $templateService->getTemplateWithVars('messages.mmsg_preview', [
            'role' => $roleLabel,
            'datetime' => $scheduledAt->format('d.m.Y H:i'),
            'type' => $typeLabel,
            'message' => $messageText,
        ], $locale) ?? "Preview: {$roleLabel}, {$scheduledAt->format('d.m.Y H:i')}, {$messageText}";

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.mmsg_edit', $locale) ?? 'Edit message',
                    callback_data: 'mmsg:edit'
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.mmsg_save', $locale) ?? 'Save broadcast',
                    callback_data: 'mmsg:save'
                ),
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.mmsg_cancel', $locale) ?? 'Cancel',
                    callback_data: 'mmsg:cancel'
                ),
            );

        $bot->sendMessage(
            text: $preview,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Handle normal ticket message
     */
    protected function handleTicketMessage(Nutgram $bot, TelegramUser $user, $message): void
    {
        $ticketService = app(TicketService::class);
        $templateService = app(TemplateService::class);
        $telegramService = app(TelegramService::class);

        // Get or create active ticket
        $ticket = $ticketService->getUserActiveTicket($user);

        if (! $ticket) {
            // Employees must select a project before the ticket is created
            if ($user->isEmployee()) {
                $this->askEmployeeForProject($bot, $user, $message, app(ConversationService::class));

                return;
            }

            // Create new ticket
            $subject = $this->getMessageText($message);
            $ticket = $ticketService->createTicket($user, $subject);

            // Update last client message time
            $ticket->update(['last_client_message_at' => now()]);

            // Create forum topic
            $this->createForumTopic($ticket, $user, $telegramService);

            // forum_topic_id is written by createForumTopic() on a separate instance
            $ticket->refresh();

            // Schedule reminder job AFTER forum topic is created and refreshed
            $telegramService->scheduleReminderJob($ticket);

            // Send confirmation to user with explicit "operator called" message
            $operatorMessage = $templateService->getTemplate('messages.operator_called', $user->locale_id)
                ?? 'Operator called! Your ticket has been sent to the support team. Please wait for a response.';

            $confirmMessage = $templateService->getTemplate('messages.ticket_created', $user->locale_id)
                ?? 'Your ticket has been sent to the support team. Please wait for a response.';

            // Combine messages
            $fullMessage = $operatorMessage."\n\n".$confirmMessage;

            // Add "Problem solved" button
            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        $templateService->getTemplate('button.problem_solved', $user->locale_id) ?? '✅ Problem solved',
                        callback_data: "close_ticket:{$ticket->id}"
                    )
                );

            $bot->sendMessage(
                text: $fullMessage,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );
        }

        // Forward message to service chat topic
        $this->forwardToServiceChat($ticket, $message, $telegramService);

        // Suggest closing the ticket if the message contains a grateful/closing keyword
        $messageText = $this->getMessageText($message);
        if ($this->messageMatchesCloseKeyword($messageText, $user, $templateService)) {
            $this->sendCloseSuggestion($bot, $ticket, $user, $templateService);
        }

        // Check after-hours and send auto-reply if needed
        $this->handleAfterHoursAutoReply($user, $ticket, $bot);

        // Log message
        BotLog::logEvent(
            eventType: 'message',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: $ticket->id,
            payload: [
                'text' => $this->getMessageText($message),
                'has_photo' => ! empty($message->photo),
                'has_document' => ! empty($message->document),
            ]
        );
    }

    /**
     * Create or reuse forum topic for ticket
     */
    protected function createForumTopic(Ticket $ticket, TelegramUser $user, TelegramService $telegramService): void
    {
        $serviceChatId = config('nutgram.service_chat_id');

        if (! $serviceChatId) {
            Log::warning('Service chat not configured, cannot create forum topic', [
                'ticket_id' => $ticket->id,
            ]);

            return;
        }

        $ticketService = app(TicketService::class);

        // Check if user already has a permanent forum topic
        if ($user->forum_topic_id && $user->forum_topic_chat_id == $serviceChatId) {
            Log::info('Reusing existing forum topic for user', [
                'user_id' => $user->id,
                'forum_topic_id' => $user->forum_topic_id,
                'ticket_id' => $ticket->id,
            ]);

            // Update ticket with existing forum info
            $ticketService->updateForumTopic(
                $ticket,
                $user->forum_topic_id,
                $serviceChatId
            );

            // Try to send message to existing topic
            $result = $this->sendNewTicketMessage($ticket, $user, $telegramService, $serviceChatId, $user->forum_topic_id);

            // If topic was deleted, reset and fall through to create a new one
            if ($result === false) {
                Log::warning('Forum topic appears deleted, resetting and creating new one', [
                    'user_id' => $user->id,
                    'old_forum_topic_id' => $user->forum_topic_id,
                ]);

                $user->update([
                    'forum_topic_id' => null,
                    'forum_topic_chat_id' => null,
                ]);
            } else {
                return;
            }
        }

        // Create NEW permanent topic for user
        $topicName = ($user->isEmployee() ? '⚠️💼 ' : '').
            "#{$user->telegram_id} ".
            ($user->username ? "@{$user->username}" : '').
            " {$user->full_name}";

        $result = $telegramService->createForumTopic($serviceChatId, $topicName);

        if (! $result || ! isset($result['message_thread_id'])) {
            Log::error('Failed to create forum topic', [
                'ticket_id' => $ticket->id,
                'service_chat_id' => $serviceChatId,
                'topic_name' => $topicName,
            ]);

            return;
        }

        $forumTopicId = $result['message_thread_id'];

        // Save forum topic to USER (permanent)
        $user->update([
            'forum_topic_id' => $forumTopicId,
            'forum_topic_chat_id' => $serviceChatId,
        ]);

        Log::info('Created new permanent forum topic for user', [
            'user_id' => $user->id,
            'forum_topic_id' => $forumTopicId,
            'ticket_id' => $ticket->id,
        ]);

        // Update ticket with forum info
        $ticketService->updateForumTopic(
            $ticket,
            $forumTopicId,
            $serviceChatId
        );

        // Send new ticket message
        $this->sendNewTicketMessage($ticket, $user, $telegramService, $serviceChatId, $forumTopicId);
    }

    /**
     * Send new ticket message to topic and pin it
     */
    protected function sendNewTicketMessage(Ticket $ticket, TelegramUser $user, TelegramService $telegramService, int $serviceChatId, int $forumTopicId): bool
    {
        $employeePart = $user->isEmployee() ? ' (⚠️ Employee ⚠️)' : '';
        $projectPart = ($ticket->project_id && $ticket->project)
            ? "\n📁 Project: <b>{$ticket->project->name}</b>"
            : '';

        $initialMessage = "🎫 <b>New Ticket #{$ticket->id}</b>\n\n".
            "👤 User: {$user->full_name}{$employeePart}\n".
            "🆔 Telegram ID: {$user->telegram_id}\n".
            ($user->username ? "📱 Username: @{$user->username}\n" : '').
            $projectPart.
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

        // Topic was deleted or unavailable
        if (! $result) {
            Log::warning('sendNewTicketMessage failed - topic may be deleted', [
                'ticket_id' => $ticket->id,
                'forum_topic_id' => $forumTopicId,
            ]);

            return false;
        }

        // Pin the message and save message_id (but don't fail if pinning fails)
        if (isset($result['message_id'])) {
            $messageId = $result['message_id'];

            // Save pinned message ID to ticket
            $ticket->update(['pinned_message_id' => $messageId]);

            // Try to pin the message (but don't fail the entire process)
            try {
                $pinSuccess = $telegramService->pinChatMessage(
                    $serviceChatId,
                    $messageId,
                    $forumTopicId
                );

                if ($pinSuccess) {
                    Log::info('Pinned new ticket message', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $messageId,
                    ]);
                } else {
                    Log::warning('Failed to pin new ticket message (but continuing)', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $messageId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception while pinning ticket message (but continuing)', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Forward message to service chat
     */
    protected function forwardToServiceChat(Ticket $ticket, $message, TelegramService $telegramService): void
    {
        if (! $ticket->forum_topic_id || ! $ticket->service_chat_id) {
            Log::error('Cannot forward: missing forum topic or service chat', [
                'ticket_id' => $ticket->id,
                'has_forum_topic' => ! empty($ticket->forum_topic_id),
                'has_service_chat' => ! empty($ticket->service_chat_id),
            ]);

            return;
        }

        // Copy message to topic (preserve formatting)
        $result = $telegramService->copyMessageToTopic(
            $ticket->service_chat_id,
            $message->chat->id,
            $message->message_id,
            $ticket->forum_topic_id
        );

        if ($result) {
            return;
        }

        // Forward failed — topic may have been deleted. Try to recreate.
        Log::warning('Forward failed, attempting to recreate forum topic', [
            'ticket_id' => $ticket->id,
            'old_forum_topic_id' => $ticket->forum_topic_id,
        ]);

        $user = $ticket->user;
        if (! $user) {
            return;
        }

        // Reset user's forum topic and recreate
        $user->update(['forum_topic_id' => null, 'forum_topic_chat_id' => null]);
        $this->createForumTopic($ticket, $user, $telegramService);
        $ticket->refresh();

        // Retry forward with new topic
        if ($ticket->forum_topic_id) {
            $retryResult = $telegramService->copyMessageToTopic(
                $ticket->service_chat_id,
                $message->chat->id,
                $message->message_id,
                $ticket->forum_topic_id
            );

            if (! $retryResult) {
                Log::error('Failed to forward even after topic recreation', [
                    'ticket_id' => $ticket->id,
                ]);
            }
        }
    }

    /**
     * Ask employee to select a project before creating a ticket.
     * Saves original message data in conversation state for later processing.
     */
    protected function askEmployeeForProject(Nutgram $bot, TelegramUser $user, $message, $conversationService): void
    {
        $projects = Project::getActive();

        if ($projects->isEmpty()) {
            // No active projects configured — fall through to normal ticket creation
            Log::warning('No active projects found, creating ticket without project selection', [
                'user_id' => $user->id,
            ]);
            $this->createTicketAndNotify(
                $bot,
                $user,
                $this->getMessageText($message),
                $message->chat->id,
                $message->message_id,
                null
            );

            return;
        }

        // Save original message data so we can copy it to forum after project selection
        $conversationService->setState($user, 'awaiting_project_selection', [
            'from_chat_id' => $message->chat->id,
            'message_id' => $message->message_id,
            'subject' => $this->getMessageText($message),
        ], 30);

        // Build project selection keyboard (2 buttons per row)
        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ($projects as $project) {
            $row[] = InlineKeyboardButton::make(
                $project->name,
                callback_data: "select_project:{$project->id}"
            );

            if (count($row) === 2) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if (! empty($row)) {
            $keyboard->addRow(...$row);
        }

        $templateService = app(TemplateService::class);
        $promptMessage = $templateService->getTemplate('messages.select_project', $user->locale_id)
            ?? '📂 Please select the project your request relates to:';

        $bot->sendMessage(
            text: $promptMessage,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );

        Log::info('Employee prompted to select project', [
            'user_id' => $user->id,
            'projects_count' => $projects->count(),
        ]);
    }

    /**
     * Handle case when employee sends a text message while in awaiting_project_selection state.
     * Simply resend the project selection keyboard.
     */
    protected function handleProjectSelectionMessage(Nutgram $bot, TelegramUser $user, $conversationService): void
    {
        $projects = Project::getActive();

        if ($projects->isEmpty()) {
            $conversationService->clearState($user);

            return;
        }

        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ($projects as $project) {
            $row[] = InlineKeyboardButton::make(
                $project->name,
                callback_data: "select_project:{$project->id}"
            );

            if (count($row) === 2) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if (! empty($row)) {
            $keyboard->addRow(...$row);
        }

        $templateService = app(TemplateService::class);
        $promptMessage = $templateService->getTemplate('messages.select_project', $user->locale_id)
            ?? '📂 Please select the project your request relates to:';

        $bot->sendMessage(
            text: $promptMessage,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Create ticket directly (used when project is already known or not required).
     * Extracted to avoid duplication between normal flow and post-project-selection flow.
     */
    public function createTicketAndNotify(
        Nutgram $bot,
        TelegramUser $user,
        string $subject,
        int $fromChatId,
        int $originalMessageId,
        ?int $projectId = null
    ): void {
        $ticketService = app(TicketService::class);
        $templateService = app(TemplateService::class);
        $telegramService = app(TelegramService::class);

        // Create ticket with optional project
        $ticket = $ticketService->createTicket($user, $subject);

        $ticket->update([
            'last_client_message_at' => now(),
            'project_id' => $projectId,
        ]);

        // Create forum topic
        $this->createForumTopic($ticket, $user, $telegramService);
        $ticket->refresh();

        Log::info('Ticket created after project selection', [
            'ticket_id' => $ticket->id,
            'project_id' => $projectId,
            'forum_topic_id' => $ticket->forum_topic_id,
        ]);

        // Schedule reminder job
        $telegramService->scheduleReminderJob($ticket);

        // Send confirmation to user
        $operatorMessage = $templateService->getTemplate('messages.operator_called', $user->locale_id)
            ?? 'Operator called! Your ticket has been sent to the support team. Please wait for a response.';
        $confirmMessage = $templateService->getTemplate('messages.ticket_created', $user->locale_id)
            ?? 'Your ticket has been sent to the support team. Please wait for a response.';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.problem_solved', $user->locale_id) ?? '✅ Problem solved',
                    callback_data: "close_ticket:{$ticket->id}"
                )
            );

        $bot->sendMessage(
            text: $operatorMessage."\n\n".$confirmMessage,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );

        // Forward the original message to the forum topic
        if ($ticket->forum_topic_id && $ticket->service_chat_id) {
            $telegramService->copyMessageToTopic(
                $ticket->service_chat_id,
                $fromChatId,
                $originalMessageId,
                $ticket->forum_topic_id
            );
        }

        // Check after-hours and send auto-reply if needed
        $this->handleAfterHoursAutoReply($user, $ticket, $bot);

        // Log
        BotLog::logEvent(
            eventType: 'message',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: $ticket->id,
            payload: [
                'text' => $subject,
                'project_id' => $projectId,
                'via_project_selection' => true,
            ]
        );
    }

    /**
     * Handle after-hours auto-reply to client
     */
    protected function handleAfterHoursAutoReply(TelegramUser $user, Ticket $ticket, Nutgram $bot): void
    {
        $scheduleService = app(WorkScheduleService::class);

        if ($scheduleService->isWorkingHoursNow()) {
            return;
        }

        // Record after-hours message for reminder
        $scheduleService->recordAfterHoursMessage($user, $ticket, false);

        // Mark the forwarded message in the forum topic as received outside working hours
        if ($ticket->forum_topic_id && $ticket->service_chat_id) {
            $telegramService = app(TelegramService::class);
            $now = now()->timezone(config('bot.work_schedule.timezone'));
            $telegramService->sendMessageToTopic(
                $ticket->service_chat_id,
                $ticket->forum_topic_id,
                "⏰ <i>Message received outside working hours ({$now->format('H:i')})</i>"
            );
        }

        // Check if auto-reply should be sent (not yet sent in this period)
        if (! $scheduleService->shouldSendAutoReply($user)) {
            Log::info('After-hours auto-reply already sent in this period', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // Get auto-reply text
        $autoReplyText = $scheduleService->getAutoReplyText($user);

        if (! $autoReplyText) {
            Log::warning('No after-hours auto-reply template configured');

            return;
        }

        // Send auto-reply to client
        $telegramService = app(TelegramService::class);
        $telegramService->sendMessageToUser($bot->chatId(), $autoReplyText);

        // Mark auto-reply as sent
        $scheduleService->recordAfterHoursMessage($user, $ticket, true);

        Log::info('After-hours auto-reply sent to client', [
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Handle schedule time input (HH:mm-HH:mm)
     */
    protected function handleScheduleTimeInput(Nutgram $bot, TelegramUser $user, $message, ?array $stateData, $conversationService): void
    {
        $input = trim($message->text ?? '');
        $dayOfWeek = $stateData['day_of_week'] ?? null;

        if (! $dayOfWeek) {
            $conversationService->clearState($user);

            return;
        }

        // Parse time range
        if (! preg_match('/^(\d{2}:\d{2})\s*[-\x{2013}]\s*(\d{2}:\d{2})$/u', $input, $matches)) {
            $bot->sendMessage(
                text: '❌ Invalid format. Please use: <code>09:00-18:00</code>',
                parse_mode: 'HTML'
            );

            return;
        }

        $startTime = $matches[1];
        $endTime = $matches[2];

        if (! $this->isValidTime($startTime) || ! $this->isValidTime($endTime)) {
            $bot->sendMessage(text: '❌ Invalid time values. Use HH:mm format (00:00-23:59).');

            return;
        }

        if ($startTime >= $endTime) {
            $bot->sendMessage(text: '❌ Start time must be before end time.');

            return;
        }

        // Save schedule
        WorkSchedule::updateOrCreate(
            ['day_of_week' => $dayOfWeek],
            ['start_time' => $startTime, 'end_time' => $endTime, 'is_working_day' => true]
        );

        $conversationService->clearState($user);

        $dayName = ucfirst(WorkSchedule::DAY_NAMES[$dayOfWeek] ?? '?');
        $bot->sendMessage(
            text: "✅ <b>{$dayName}</b> set to: {$startTime} – {$endTime}",
            parse_mode: 'HTML'
        );

        // Return to day selection so admin can configure the next day without extra navigation
        ScheduleCommand::showDaySelection($bot);
    }

    /**
     * Handle override date input (DD.MM.YYYY)
     */
    protected function handleOverrideDateInput(Nutgram $bot, TelegramUser $user, $message, ?array $stateData, $conversationService): void
    {
        $input = trim($message->text ?? '');

        try {
            $date = Carbon::createFromFormat('d.m.Y', $input);

            if (! $date || $date->isBefore(Carbon::today())) {
                $bot->sendMessage(text: '❌ Date must be today or in the future. Use format: <code>25.12.2025</code>', parse_mode: 'HTML');

                return;
            }
        } catch (\Exception $e) {
            $bot->sendMessage(text: '❌ Invalid date format. Use: <code>25.12.2025</code>', parse_mode: 'HTML');

            return;
        }

        // Clear state and show type selection buttons
        $conversationService->clearState($user);

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🟢 Working day', callback_data: "schedule:ovr_type:{$date->toDateString()}:working"),
                InlineKeyboardButton::make('🔴 Day off', callback_data: "schedule:ovr_type:{$date->toDateString()}:dayoff")
            )
            ->addRow(InlineKeyboardButton::make('⬅️ Cancel', callback_data: 'schedule:back'));

        $bot->sendMessage(
            text: "📌 Override for <b>{$input}</b>\n\nChoose type:",
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Handle override time input (HH:mm-HH:mm)
     */
    protected function handleOverrideTimeInput(Nutgram $bot, TelegramUser $user, $message, ?array $stateData, $conversationService): void
    {
        $input = trim($message->text ?? '');
        $dateStr = $stateData['override_date'] ?? null;

        if (! $dateStr) {
            $conversationService->clearState($user);

            return;
        }

        if (! preg_match('/^(\d{2}:\d{2})\s*[-\x{2013}]\s*(\d{2}:\d{2})$/u', $input, $matches)) {
            $bot->sendMessage(
                text: '❌ Invalid format. Please use: <code>09:00-15:00</code>',
                parse_mode: 'HTML'
            );

            return;
        }

        $startTime = $matches[1];
        $endTime = $matches[2];

        if (! $this->isValidTime($startTime) || ! $this->isValidTime($endTime) || $startTime >= $endTime) {
            $bot->sendMessage(text: '❌ Invalid time range.');

            return;
        }

        $date = Carbon::parse($dateStr);
        $description = $stateData['description'] ?? null;

        WorkScheduleOverride::updateOrCreate(
            ['date' => $dateStr],
            [
                'is_working_day' => true,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'description' => $description,
            ]
        );

        $conversationService->clearState($user);

        $bot->sendMessage(
            text: "✅ Override for <b>{$date->format('d.m.Y')}</b>: {$startTime} – {$endTime}",
            parse_mode: 'HTML'
        );

        ScheduleCommand::showMainMenu($bot);
    }

    /**
     * Validate time string HH:mm
     */
    protected function isValidTime(string $time): bool
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
            return false;
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];

        return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
    }

    /**
     * Check if the message text contains any close keyword across ALL locales.
     * Normalises to lowercase and checks for substring matches.
     */
    protected function messageMatchesCloseKeyword(string $text, TelegramUser $user, TemplateService $templateService): bool
    {
        $normalized = mb_strtolower(trim($text));

        if (empty($normalized)) {
            return false;
        }

        // Collect keywords from all locales so language doesn't matter
        $allKeywords = [];
        foreach (['uk', 'ru', 'en'] as $code) {
            $locale = $templateService->getLocaleByCode($code);
            if ($locale) {
                $allKeywords = array_merge($allKeywords, $templateService->getCloseKeywords($locale->id));
            }
        }

        foreach ($allKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a close-ticket suggestion with Yes / No inline buttons.
     * The "Yes" button reuses the existing close_ticket callback handler.
     */
    protected function sendCloseSuggestion(Nutgram $bot, Ticket $ticket, TelegramUser $user, TemplateService $templateService): void
    {
        $suggestionText = $templateService->getTemplate('messages.close_suggestion', $user->locale_id)
            ?? '✅ It seems your issue has been resolved. Would you like to close this ticket?';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.close_suggest_yes', $user->locale_id) ?? '✅ Yes, close',
                    callback_data: "close_ticket:{$ticket->id}"
                ),
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.close_suggest_no', $user->locale_id) ?? '💬 No, continue',
                    callback_data: "close_suggest_no:{$ticket->id}"
                )
            );

        $bot->sendMessage(
            text: $suggestionText,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );

        Log::info('Close suggestion sent to client', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get message text with support for different message types
     */
    protected function getMessageText($message): string
    {
        // First check caption (for media with text description)
        if (! empty($message->caption)) {
            return $message->caption;
        }

        // Then check regular text
        if (! empty($message->text)) {
            return $message->text;
        }

        // For media without caption, return type labels
        if (! empty($message->photo)) {
            return '[Photo without caption]';
        }

        if (! empty($message->document)) {
            $fileName = $message->document->file_name ?? 'unknown';

            return "[Document: {$fileName}]";
        }

        if (! empty($message->voice)) {
            return '[Voice message]';
        }

        if (! empty($message->video)) {
            return '[Video without caption]';
        }

        if (! empty($message->video_note)) {
            return '[Video note]';
        }

        if (! empty($message->audio)) {
            $title = $message->audio->title ?? 'Unknown';

            return "[Audio: {$title}]";
        }

        if (! empty($message->sticker)) {
            $emoji = $message->sticker->emoji ?? '🎭';

            return "[Sticker: {$emoji}]";
        }

        return '[Unsupported message type]';
    }
}
