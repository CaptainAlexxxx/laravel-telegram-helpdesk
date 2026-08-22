<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\TemplateService;
use App\Services\TicketService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CloseTicketCommand extends BaseCommand
{
    protected string $command = 'close_ticket';

    protected ?string $description = 'Close current support ticket';

    public function handle(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->sendMessage(
                text: 'Please start the bot first using /start'
            );

            return;
        }

        // Log command execution
        $this->logCommand($bot, $user);

        $ticketService = app(TicketService::class);
        $templateService = app(TemplateService::class);

        // Get user's active ticket
        $activeTicket = $ticketService->getUserActiveTicket($user);

        if (! $activeTicket) {
            $bot->sendMessage(
                text: 'You don\'t have any active tickets.',
                parse_mode: 'HTML'
            );

            return;
        }

        // Close the ticket
        $ticketService->closeTicket($activeTicket);

        // Get close message
        $closeMessage = $templateService->getTemplate('messages.ticket_closed', $user->locale_id)
            ?? 'Your ticket has been closed. Thank you for contacting us!';

        $bot->sendMessage(
            text: $closeMessage,
            parse_mode: 'HTML'
        );

        // Ask for rating
        $this->askForRating($bot, $activeTicket->id, $user->locale_id);
    }

    /**
     * Ask user to rate the support
     */
    protected function askForRating(Nutgram $bot, int $ticketId, ?int $localeId): void
    {
        $templateService = app(TemplateService::class);

        $ratingMessage = $templateService->getTemplate('messages.rate_request', $localeId)
            ?? 'Please rate the quality of support from 1 to 5:';

        // Create rating buttons
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('1', callback_data: "rate:{$ticketId}:1"),
                InlineKeyboardButton::make('2', callback_data: "rate:{$ticketId}:2"),
                InlineKeyboardButton::make('3', callback_data: "rate:{$ticketId}:3"),
            )
            ->addRow(
                InlineKeyboardButton::make('4', callback_data: "rate:{$ticketId}:4"),
                InlineKeyboardButton::make('5', callback_data: "rate:{$ticketId}:5"),
            );

        $bot->sendMessage(
            text: $ratingMessage,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }
}
