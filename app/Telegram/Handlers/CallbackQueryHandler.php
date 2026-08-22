<?php

namespace App\Telegram\Handlers;

use App\Jobs\SendBroadcastJob;
use App\Models\BotLog;
use App\Models\Broadcast;
use App\Models\Project;
use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleOverride;
use App\Services\AgentGuard;
use App\Services\ConversationService;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\TicketService;
use App\Services\WorkScheduleService;
use App\Telegram\Commands\FaqCommand;
use App\Telegram\Commands\ScheduleCommand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CallbackQueryHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $callbackQuery = $bot->callbackQuery();
        $data = $callbackQuery->data;

        // Log callback query
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if ($user && config('bot.logging.log_callbacks', true)) {
            BotLog::logEvent(
                eventType: 'callback',
                eventSource: 'telegram_user',
                userId: $user->id,
                ticketId: null,
                payload: [
                    'callback_data' => $data,
                    'message_id' => $callbackQuery->message?->message_id,
                ]
            );
        }

        // Parse callback data
        $parts = explode(':', $data);
        $action = $parts[0] ?? null;

        match ($action) {
            'rate' => $this->handleRating($bot, $parts),
            'rate_comment' => $this->handleRatingComment($bot, $parts),
            'close_ticket' => $this->handleCloseTicket($bot, $parts),
            'close_suggest_no' => $this->handleCloseSuggestNo($bot, $parts),
            'agent_close' => $this->handleAgentCloseTicket($bot, $parts),
            'select_project' => $this->handleProjectSelection($bot, $parts),
            'faq' => $parts[1] === 'back' ? $this->handleFaqBack($bot) : $this->handleFaq($bot, $parts),
            'menu' => $this->handleMenu($bot, $parts),
            'mmsg' => $this->handleMmsgCallback($bot, $parts),
            'schedule' => $this->handleSchedule($bot, $parts),
            'afterhours_remind' => $this->handleAfterHoursRemind($bot, $parts),
            'broadcast' => $this->handleBroadcastCallback($bot, $parts),
            default => $this->answerCallback($bot, 'Unknown action'),
        };
    }

    /**
     * Safe answer callback query with timeout protection
     */
    protected function answerCallback(Nutgram $bot, string $text, bool $showAlert = false): void
    {
        try {
            $bot->answerCallbackQuery($text, $showAlert);
        } catch (\Exception $e) {
            Log::warning('Failed to answer callback query (timeout)', [
                'text' => $text,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle employee project selection callback.
     * Creates the ticket and proceeds with the standard flow.
     */
    protected function handleProjectSelection(Nutgram $bot, array $parts): void
    {
        $projectId = (int) ($parts[1] ?? 0);

        if (! $projectId) {
            $this->answerCallback($bot, 'Invalid project');

            return;
        }

        $project = Project::find($projectId);

        if (! $project || ! $project->is_active) {
            $this->answerCallback($bot, 'Project not found or inactive');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->answerCallback($bot, 'User not found');

            return;
        }

        $conversationService = app(ConversationService::class);

        // State must be awaiting_project_selection with saved original message data.
        // If missing, session expired — ask user to resend their message.
        if (! $conversationService->isInState($user, 'awaiting_project_selection')) {
            $this->answerCallback($bot, '');
            $templateService = app(TemplateService::class);
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.session_expired', $user->locale_id)
                    ?? '⏰ Session expired. Please send your message again.',
                parse_mode: 'HTML'
            );

            return;
        }

        $stateData = $conversationService->getStateData($user);

        $fromChatId = $stateData['from_chat_id'] ?? null;
        $originalMsgId = $stateData['message_id'] ?? null;
        $subject = $stateData['subject'] ?? '';

        if (! $fromChatId || ! $originalMsgId) {
            Log::error('Project selection: missing state data', [
                'user_id' => $user->id,
                'state_data' => $stateData,
            ]);
            $conversationService->clearState($user);
            $this->answerCallback($bot, 'Session expired, please send your message again');

            return;
        }

        // Clear state before creating ticket to avoid race conditions
        $conversationService->clearState($user);

        // Remove the project selection keyboard from the prompt message
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $this->answerCallback($bot, '');

        Log::info('Employee selected project, creating ticket', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'project' => $project->name,
        ]);

        // Delegate ticket creation to MessageHandler (reuses all existing logic)
        $messageHandler = app(MessageHandler::class);
        $messageHandler->createTicketAndNotify(
            $bot,
            $user,
            $subject,
            $fromChatId,
            $originalMsgId,
            $project->id
        );
    }

    /**
     * Handle rating callback
     */
    protected function handleRating(Nutgram $bot, array $parts): void
    {
        $ticketId = (int) ($parts[1] ?? 0);
        $rating = (int) ($parts[2] ?? 0);

        if (! $ticketId || ! $rating || $rating < 1 || $rating > 5) {
            $this->answerCallback($bot, 'Invalid rating');

            return;
        }

        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            $this->answerCallback($bot, 'Ticket not found');

            return;
        }

        // Save rating
        $ticketService = app(TicketService::class);
        $ticketService->addReview($ticket, $rating);

        // Remove keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        // Answer callback
        $this->answerCallback($bot, 'Thank you for your rating!');

        // Send thank you message with comment button
        $templateService = app(TemplateService::class);
        $user = TelegramUser::where('telegram_id', $bot->userId())->first();

        $thankYouMessage = $templateService->getTemplate('messages.rating_thanks', $user->locale_id)
            ?? 'Thank you for your feedback!';

        // Add button to leave comment
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.leave_comment', $user->locale_id) ?? 'Leave a comment',
                    callback_data: "rate_comment:{$ticketId}"
                )
            );

        $bot->sendMessage(
            text: $thankYouMessage,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Handle close ticket from user
     */
    protected function handleCloseTicket(Nutgram $bot, array $parts): void
    {
        $ticketId = (int) ($parts[1] ?? 0);

        if (! $ticketId) {
            $this->answerCallback($bot, 'Invalid ticket');

            return;
        }

        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            $this->answerCallback($bot, 'Ticket not found');

            return;
        }

        // Verify user owns the ticket
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || $ticket->user_id !== $user->id) {
            $this->answerCallback($bot, 'Unauthorized');

            return;
        }

        // Close ticket
        $ticketService = app(TicketService::class);
        $ticketService->closeTicket($ticket);

        // Notify agents in service chat
        $this->notifyAgentsAboutClosure($ticket, 'client');

        // Remove keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        // Answer callback
        $this->answerCallback($bot, 'Ticket closed');

        // Send confirmation
        $templateService = app(TemplateService::class);
        $closeMessage = $templateService->getTemplate('messages.ticket_closed', $user->locale_id)
            ?? '✅ Your ticket has been closed. Thank you!';

        $bot->sendMessage(
            text: $closeMessage,
            parse_mode: 'HTML'
        );

        // Ask for rating
        $this->askUserForRating($ticket);
    }

    /**
     * Handle "No, continue" on the close-ticket suggestion
     */
    protected function handleCloseSuggestNo(Nutgram $bot, array $parts): void
    {
        $ticketId = (int) ($parts[1] ?? 0);

        if (! $ticketId) {
            $this->answerCallback($bot, 'Invalid ticket');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->answerCallback($bot, 'User not found');

            return;
        }

        // Remove the suggestion keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $this->answerCallback($bot, '');

        // Send a short reassurance message
        $templateService = app(TemplateService::class);
        $declinedMessage = $templateService->getTemplate('messages.close_suggest_declined', $user->locale_id)
            ?? '💬 No problem! We are still here if you need anything.';

        $bot->sendMessage(
            text: $declinedMessage,
            parse_mode: 'HTML'
        );
    }

    /**
     * Handle close ticket from agent
     */
    protected function handleAgentCloseTicket(Nutgram $bot, array $parts): void
    {
        $ticketId = (int) ($parts[1] ?? 0);

        if (! $ticketId) {
            $this->answerCallback($bot, 'Invalid ticket');

            return;
        }

        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            $this->answerCallback($bot, 'Ticket not found');

            return;
        }

        // Get agent
        $telegramId = $bot->userId();
        $agent = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $agent) {
            $this->answerCallback($bot, 'User not found');

            return;
        }

        // Check agent permissions
        $agentGuard = app(AgentGuard::class);

        if (! $agentGuard->canPerformAgentAction($agent)) {
            $this->answerCallback($bot, 'Access denied - agents only');

            return;
        }

        // Close ticket
        $ticketService = app(TicketService::class);
        $ticketService->closeTicket($ticket, $agent->id);

        // Notify agents in service chat
        $this->notifyAgentsAboutClosure($ticket, 'agent', $agent);

        // Remove keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        // Answer callback
        $this->answerCallback($bot, 'Ticket closed by agent');

        // Notify user
        try {
            $templateService = app(TemplateService::class);
            $closeMessage = $templateService->getTemplate('messages.ticket_closed', $ticket->user->locale_id)
                ?? 'Your ticket has been closed by support team. Thank you!';

            $telegramService->sendMessageToUser(
                $ticket->user->telegram_id,
                $closeMessage
            );

            // Ask user for rating
            $this->askUserForRating($ticket);

        } catch (\Exception $e) {
            Log::error('Failed to notify user about ticket closure', [
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user->telegram_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ask user for rating with error handling
     */
    protected function askUserForRating(Ticket $ticket): void
    {
        try {
            $templateService = app(TemplateService::class);
            $telegramService = app(TelegramService::class);

            $ratingMessage = $templateService->getTemplate('messages.rate_request', $ticket->user->locale_id)
                ?? 'Please rate the quality of support from 1 to 5:';

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '1', 'callback_data' => "rate:{$ticket->id}:1"],
                        ['text' => '2', 'callback_data' => "rate:{$ticket->id}:2"],
                        ['text' => '3', 'callback_data' => "rate:{$ticket->id}:3"],
                    ],
                    [
                        ['text' => '4', 'callback_data' => "rate:{$ticket->id}:4"],
                        ['text' => '5', 'callback_data' => "rate:{$ticket->id}:5"],
                    ],
                ],
            ];

            $telegramService->sendMessageWithKeyboard(
                $ticket->user->telegram_id,
                $ratingMessage,
                $keyboard['inline_keyboard']
            );

        } catch (\Exception $e) {
            Log::error('Failed to ask user for rating', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle FAQ callback
     */
    protected function handleFaq(Nutgram $bot, array $parts): void
    {
        $faqNumber = (int) ($parts[1] ?? 0);

        if (! $faqNumber) {
            $this->answerCallback($bot, 'Invalid FAQ number');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->answerCallback($bot, 'User not found');

            return;
        }

        $templateService = app(TemplateService::class);

        // Get FAQ question and answer
        $question = $templateService->getTemplate("faq.question_{$faqNumber}", $user->locale_id);
        $answer = $templateService->getTemplate("faq.answer_{$faqNumber}", $user->locale_id);

        if (! $question || ! $answer) {
            $this->answerCallback($bot, 'FAQ not found');

            return;
        }

        // Send answer
        $responseText = "<b>{$question}</b>\n\n{$answer}";

        // Create back button
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    $templateService->getTemplate('button.back_to_faq', $user->locale_id) ?? 'Back to FAQ',
                    callback_data: 'faq:back'
                )
            );

        $bot->sendMessage(
            text: $responseText,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );

        $this->answerCallback($bot, '');

        // Log FAQ view
        BotLog::logEvent(
            eventType: 'callback',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: null,
            payload: [
                'action' => 'faq_view',
                'faq_number' => $faqNumber,
            ]
        );
    }

    /**
     * Handle FAQ back button
     */
    protected function handleFaqBack(Nutgram $bot): void
    {
        // Simulate /faq command
        $faqCommand = new FaqCommand;
        $faqCommand->handle($bot);

        $this->answerCallback($bot, '');
    }

    /**
     * Handle menu navigation
     */
    protected function handleMenu(Nutgram $bot, array $parts): void
    {
        $menuType = $parts[1] ?? 'main';

        // For now, just acknowledge
        $this->answerCallback($bot, 'Menu navigation');

        // You can implement main menu here later
    }

    /**
     * Handle rating comment button
     */
    protected function handleRatingComment(Nutgram $bot, array $parts): void
    {
        $ticketId = (int) ($parts[1] ?? 0);

        if (! $ticketId) {
            $this->answerCallback($bot, 'Invalid ticket');

            return;
        }

        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            $this->answerCallback($bot, 'Ticket not found');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->answerCallback($bot, 'User not found');

            return;
        }

        // Set conversation state to awaiting review comment
        $conversationService = app(ConversationService::class);
        $conversationService->setState($user, 'awaiting_review_comment', [
            'ticket_id' => $ticketId,
        ]);

        // Remove keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $this->answerCallback($bot, '');

        // Prompt user to enter comment
        $templateService = app(TemplateService::class);
        $promptMessage = $templateService->getTemplate('messages.enter_review_comment', $user->locale_id)
            ?? 'Please enter your comment about the support quality:';

        $bot->sendMessage(
            text: $promptMessage,
            parse_mode: 'HTML'
        );

        // Log callback
        BotLog::logEvent(
            eventType: 'callback',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: $ticketId,
            payload: ['action' => 'request_review_comment']
        );
    }

    /**
     * Dispatch mmsg callback actions
     */
    protected function handleMmsgCallback(Nutgram $bot, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'role' => $this->handleMmsgRoleSelected($bot, $parts),
            'save' => $this->handleMmsgSave($bot),
            'edit' => $this->handleMmsgEdit($bot),
            'cancel' => $this->handleMmsgCancel($bot),
            default => $this->answerCallback($bot, 'Unknown mmsg action'),
        };
    }

    /**
     * Role selected — move to awaiting message state
     */
    protected function handleMmsgRoleSelected(Nutgram $bot, array $parts): void
    {
        $role = $parts[2] ?? null;

        $allowedRoles = ['all', 'client', 'employee', 'agent', 'admin'];
        if (! in_array($role, $allowedRoles)) {
            $this->answerCallback($bot, 'Invalid role');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->isAdmin()) {
            $this->answerCallback($bot, 'Access denied');

            return;
        }

        $conversationService = app(ConversationService::class);

        if (! $conversationService->isInState($user, 'mmsg_select_role')) {
            $this->answerCallback($bot, 'Session expired. Use /mmsg to start again.');

            return;
        }

        $conversationService->setState($user, 'mmsg_awaiting_message', ['role' => $role], 60);

        $this->answerCallback($bot, '');

        $templateService = app(TemplateService::class);
        $locale = $user->locale_id;
        $roleLabel = $templateService->getTemplate('role.'.$role, $locale) ?? $role;

        $text = $templateService->getTemplateWithVars('messages.mmsg_role_selected', ['role' => $roleLabel], $locale)
            ?? "Recipients: {$roleLabel}. Step 2/3. Send a message for the broadcast.";

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML'
        );
    }

    /**
     * Save broadcast and dispatch job
     */
    protected function handleMmsgSave(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->isAdmin()) {
            $this->answerCallback($bot, 'Access denied');

            return;
        }

        $conversationService = app(ConversationService::class);

        if (! $conversationService->isInState($user, 'mmsg_preview')) {
            $this->answerCallback($bot, 'Session expired. Use /mmsg to start again.');

            return;
        }

        $stateData = $conversationService->getStateData($user);

        // Create broadcast record
        $broadcast = Broadcast::create([
            'created_by' => $user->id,
            'role' => $stateData['role'] ?? 'all',
            'message_text' => $stateData['message_text'] ?? null,
            'photo_file_id' => $stateData['photo_file_id'] ?? null,
            'caption' => $stateData['caption'] ?? null,
            'scheduled_at' => $stateData['scheduled_at'],
            'status' => 'pending',
        ]);

        // Dispatch job at the scheduled time
        $scheduledAt = Carbon::parse($stateData['scheduled_at']);
        SendBroadcastJob::dispatch($broadcast)->delay($scheduledAt);

        $conversationService->clearState($user);

        $this->answerCallback($bot, '');

        // Remove preview keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $templateService = app(TemplateService::class);
        $text = $templateService->getTemplateWithVars('messages.mmsg_saved', [
            'datetime' => $scheduledAt->format('d.m.Y H:i'),
            'id' => $broadcast->id,
        ], $user->locale_id) ?? "Broadcast saved! Will be sent: {$scheduledAt->format('d.m.Y H:i')}. ID: #{$broadcast->id}";

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML'
        );

        Log::info('Broadcast created', [
            'broadcast_id' => $broadcast->id,
            'created_by' => $user->id,
            'scheduled_at' => $stateData['scheduled_at'],
            'role' => $stateData['role'],
        ]);
    }

    /**
     * Edit message — return to awaiting_message state, keep role/date/time
     */
    protected function handleMmsgEdit(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->isAdmin()) {
            $this->answerCallback($bot, 'Access denied');

            return;
        }

        $conversationService = app(ConversationService::class);
        $stateData = $conversationService->getStateData($user) ?? [];

        // Keep role and scheduled data, reset only message fields
        unset($stateData['message_text'], $stateData['photo_file_id'], $stateData['caption']);

        $conversationService->setState($user, 'mmsg_awaiting_message', $stateData, 60);

        $this->answerCallback($bot, '');

        $templateService = app(TemplateService::class);
        $bot->sendMessage(
            text: $templateService->getTemplate('messages.mmsg_edit_prompt', $user->locale_id) ?? 'Send a new message for the broadcast.',
            parse_mode: 'HTML'
        );
    }

    /**
     * Cancel broadcast creation
     */
    protected function handleMmsgCancel(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if ($user) {
            $conversationService = app(ConversationService::class);
            $conversationService->clearState($user);
        }

        $this->answerCallback($bot, '');

        // Remove keyboard from current message
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $templateService = app(TemplateService::class);
        $bot->sendMessage(text: $templateService->getTemplate('messages.mmsg_cancelled', $user?->locale_id) ?? 'Broadcast creation cancelled.');
    }

    /**
     * Dispatch broadcast management callbacks
     */
    protected function handleBroadcastCallback(Nutgram $bot, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'delete' => $this->handleBroadcastDelete($bot, $parts),
            default => $this->answerCallback($bot, 'Unknown broadcast action'),
        };
    }

    /**
     * Delete (cancel) a scheduled broadcast
     */
    protected function handleBroadcastDelete(Nutgram $bot, array $parts): void
    {
        $broadcastId = (int) ($parts[2] ?? 0);

        if (! $broadcastId) {
            $this->answerCallback($bot, 'Invalid broadcast ID');

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->isAdmin()) {
            $this->answerCallback($bot, 'Access denied');

            return;
        }

        $broadcast = Broadcast::find($broadcastId);

        if (! $broadcast) {
            $this->answerCallback($bot, 'Broadcast not found');

            return;
        }

        if ($broadcast->status !== 'pending') {
            $this->answerCallback($bot, 'Broadcast is already sent or cancelled');

            return;
        }

        $broadcast->update(['status' => 'cancelled']);

        // Remove delete button from the list message
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );

        $this->answerCallback($bot, '');

        $templateService = app(TemplateService::class);
        $text = $templateService->getTemplateWithVars('messages.mmsg_deleted', ['id' => $broadcast->id], $user->locale_id)
            ?? "Broadcast #{$broadcast->id} deleted.";

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML'
        );

        Log::info('Broadcast cancelled', [
            'broadcast_id' => $broadcast->id,
            'cancelled_by' => $user->id,
        ]);
    }

    /**
     * Notify agents about ticket closure and unpin message
     */
    protected function notifyAgentsAboutClosure(Ticket $ticket, string $closedBy, ?TelegramUser $agent = null): void
    {
        if (! $ticket->forum_topic_id || ! $ticket->service_chat_id) {
            return;
        }

        try {
            $telegramService = app(TelegramService::class);

            //            // Unpin the original ticket message
            //            if ($ticket->pinned_message_id) {
            //                $telegramService->unpinChatMessage(
            //                    $ticket->service_chat_id,
            //                    $ticket->pinned_message_id,
            //                    $ticket->forum_topic_id
            //                );
            //
            //                Log::info('Unpinned closed ticket message', [
            //                    'ticket_id' => $ticket->id,
            //                    'message_id' => $ticket->pinned_message_id,
            //                ]);
            //            }

            // Send closure notification
            if ($closedBy === 'client') {
                $notificationText = "ℹ️ <b>Ticket #{$ticket->id} closed by client</b>\n\n".
                    'Client marked the issue as resolved.';
            } else {
                $agentName = $agent ? $agent->full_name : 'Agent';
                $notificationText = "ℹ️ <b>Ticket #{$ticket->id} closed by agent</b>\n\n".
                    "Closed by: {$agentName}";
            }

            $telegramService->sendMessageToTopic(
                $ticket->service_chat_id,
                $ticket->forum_topic_id,
                $notificationText,
                null
            );

            Log::info('Notified agents about ticket closure', [
                'ticket_id' => $ticket->id,
                'closed_by' => $closedBy,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify agents about ticket closure', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle schedule management callbacks
     */
    protected function handleSchedule(Nutgram $bot, array $parts): void
    {
        // Admin check
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->isAdmin()) {
            $this->answerCallback($bot, '⛔ Admin only');

            return;
        }

        $subAction = $parts[1] ?? '';

        $this->answerCallback($bot, '');

        $msgId = $bot->callbackQuery()?->message?->message_id;

        match ($subAction) {
            'view' => ScheduleCommand::showScheduleView($bot, $msgId),
            'edit_day' => ScheduleCommand::showDaySelection($bot, $msgId),
            'back' => ScheduleCommand::showMainMenu($bot, $msgId),
            'day' => $this->handleScheduleDaySelected($bot, $parts, $user, $msgId),
            'day_type' => $this->handleScheduleDayType($bot, $parts, $user, $msgId),
            'day_remove' => $this->handleScheduleDayRemove($bot, $parts, $msgId),
            'override' => $this->handleScheduleOverrideStart($bot, $user),
            'ovr_type' => $this->handleScheduleOverrideType($bot, $parts, $user, $msgId),
            'delete_override' => ScheduleCommand::showOverrideList($bot, $msgId),
            'del_ovr' => $this->handleScheduleDeleteOverride($bot, $parts, $msgId),
            default => null,
        };
    }

    /**
     * Handle day selected for schedule editing
     */
    protected function handleScheduleDaySelected(Nutgram $bot, array $parts, TelegramUser $user, ?int $msgId = null): void
    {
        $dayOfWeek = (int) ($parts[2] ?? 0);

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            return;
        }

        ScheduleCommand::showDayTypeSelection($bot, $dayOfWeek, $msgId);
    }

    /**
     * Handle day type selection (working/dayoff)
     */
    protected function handleScheduleDayType(Nutgram $bot, array $parts, TelegramUser $user, ?int $msgId = null): void
    {
        $dayOfWeek = (int) ($parts[2] ?? 0);
        $type = $parts[3] ?? '';

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            return;
        }

        if ($type === 'dayoff') {
            WorkSchedule::updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                ['is_working_day' => false, 'start_time' => '00:00', 'end_time' => '00:00']
            );

            $dayName = ucfirst(WorkSchedule::DAY_NAMES[$dayOfWeek] ?? '?');
            $bot->sendMessage(
                text: "✅ <b>{$dayName}</b> set as day off.",
                parse_mode: 'HTML'
            );

            // Return to day selection (not main menu) after setting a day
            ScheduleCommand::showDaySelection($bot, $msgId);
        } elseif ($type === 'working') {
            $conversationService = app(ConversationService::class);
            $conversationService->setState($user, 'awaiting_schedule_time', [
                'day_of_week' => $dayOfWeek,
            ]);

            $dayName = ucfirst(WorkSchedule::DAY_NAMES[$dayOfWeek] ?? '?');
            $bot->sendMessage(
                text: "⏰ Enter working hours for <b>{$dayName}</b>:\n\nFormat: <code>09:00-18:00</code>",
                parse_mode: 'HTML'
            );
        }
    }

    /**
     * Handle day schedule removal
     */
    protected function handleScheduleDayRemove(Nutgram $bot, array $parts, ?int $msgId = null): void
    {
        $dayOfWeek = (int) ($parts[2] ?? 0);

        if ($dayOfWeek < 1 || $dayOfWeek > 7) {
            return;
        }

        WorkSchedule::where('day_of_week', $dayOfWeek)->delete();

        $dayName = ucfirst(WorkSchedule::DAY_NAMES[$dayOfWeek] ?? '?');
        $bot->sendMessage(
            text: "🗑 Schedule for <b>{$dayName}</b> removed.",
            parse_mode: 'HTML'
        );

        // Return to day selection after removal
        ScheduleCommand::showDaySelection($bot, $msgId);
    }

    /**
     * Start override creation — ask for date
     */
    protected function handleScheduleOverrideStart(Nutgram $bot, TelegramUser $user): void
    {
        $conversationService = app(ConversationService::class);
        $conversationService->setState($user, 'awaiting_override_date');

        $bot->sendMessage(
            text: "📌 Enter date for override:\n\nFormat: <code>25.12.2025</code>",
            parse_mode: 'HTML'
        );
    }

    /**
     * Handle override type selection (working/dayoff)
     */
    protected function handleScheduleOverrideType(Nutgram $bot, array $parts, TelegramUser $user, ?int $msgId = null): void
    {
        $dateStr = $parts[2] ?? '';
        $type = $parts[3] ?? '';

        if (empty($dateStr) || empty($type)) {
            return;
        }

        if ($type === 'dayoff') {
            WorkScheduleOverride::updateOrCreate(
                ['date' => $dateStr],
                ['is_working_day' => false, 'start_time' => null, 'end_time' => null]
            );

            $date = Carbon::parse($dateStr);
            $bot->sendMessage(
                text: "✅ <b>{$date->format('d.m.Y')}</b> set as day off.",
                parse_mode: 'HTML'
            );

            ScheduleCommand::showMainMenu($bot, $msgId);
        } elseif ($type === 'working') {
            $conversationService = app(ConversationService::class);
            $conversationService->setState($user, 'awaiting_override_time', [
                'override_date' => $dateStr,
            ]);

            $date = Carbon::parse($dateStr);
            $bot->sendMessage(
                text: "⏰ Enter working hours for <b>{$date->format('d.m.Y')}</b>:\n\nFormat: <code>09:00-15:00</code>",
                parse_mode: 'HTML'
            );
        }
    }

    /**
     * Handle override deletion
     */
    protected function handleScheduleDeleteOverride(Nutgram $bot, array $parts, ?int $msgId = null): void
    {
        $overrideId = (int) ($parts[2] ?? 0);

        $override = WorkScheduleOverride::find($overrideId);

        if (! $override) {
            $bot->sendMessage(text: '❌ Override not found.');

            return;
        }

        $dateStr = $override->date->format('d.m.Y');
        $override->delete();

        $bot->sendMessage(
            text: "🗑 Override for <b>{$dateStr}</b> deleted.",
            parse_mode: 'HTML'
        );

        ScheduleCommand::showMainMenu($bot, $msgId);
    }

    /**
     * Handle after-hours reminder confirmation/cancellation
     */
    protected function handleAfterHoursRemind(Nutgram $bot, array $parts): void
    {
        $recordId = (int) ($parts[1] ?? 0);
        $decision = $parts[2] ?? '';

        if (! $recordId || ! in_array($decision, ['yes', 'no'])) {
            $this->answerCallback($bot, 'Invalid action');

            return;
        }

        $scheduleService = app(WorkScheduleService::class);

        if ($decision === 'yes') {
            $scheduleService->confirmReminder($recordId);
            $this->answerCallback($bot, '✅ Reminder will be sent');
        } else {
            $scheduleService->cancelReminder($recordId);
            $this->answerCallback($bot, '❌ Reminder cancelled');
        }

        // Remove keyboard
        $telegramService = app(TelegramService::class);
        $telegramService->editMessageReplyMarkup(
            $bot->chatId(),
            $bot->callbackQuery()->message->message_id,
            null
        );
    }
}
