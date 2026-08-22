<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Services\TemplateService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MyTicketsCommand extends BaseCommand
{
    protected string $command = 'my_tickets';

    protected ?string $description = 'Show my tickets';

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

        $templateService = app(TemplateService::class);

        // Get user's tickets
        $tickets = Ticket::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($tickets->isEmpty()) {
            $noTicketsMessage = $templateService->getTemplate('messages.no_tickets', $user->locale_id)
                ?? 'You have no tickets yet. Send a message to create one.';

            $bot->sendMessage(
                text: $noTicketsMessage,
                parse_mode: 'HTML'
            );

            return;
        }

        // Build tickets list message
        $message = $templateService->getTemplate('messages.my_tickets_header', $user->locale_id)
            ?? '<b>Your tickets:</b>';

        $message .= "\n\n";

        foreach ($tickets as $ticket) {
            $statusEmoji = match ($ticket->status) {
                'open' => '🟢',
                'pending' => '🟡',
                'closed' => '🔴',
                default => '⚪',
            };

            $statusText = match ($ticket->status) {
                'open' => $templateService->getTemplate('status.open', $user->locale_id) ?? 'Open',
                'pending' => $templateService->getTemplate('status.pending', $user->locale_id) ?? 'Pending',
                'closed' => $templateService->getTemplate('status.closed', $user->locale_id) ?? 'Closed',
                default => 'Unknown',
            };

            // Truncate subject to 50 characters
            $subject = mb_strlen($ticket->subject) > 50
                ? mb_substr($ticket->subject, 0, 50).'...'
                : $ticket->subject;

            $createdAt = $ticket->created_at->format('d.m.Y H:i');

            $message .= "{$statusEmoji} <b>#{$ticket->id}</b> - {$statusText}\n";
            $message .= "   {$subject}\n";
            $message .= "   Created: {$createdAt}\n";

            if ($ticket->review_rating) {
                $stars = str_repeat('⭐', $ticket->review_rating);
                $message .= "   Rating: {$stars}\n";
            }

            $message .= "\n";
        }

        // Add action buttons for open tickets
        $keyboard = InlineKeyboardMarkup::make();

        $openTickets = $tickets->where('status', '!=', 'closed');
        if ($openTickets->isNotEmpty()) {
            foreach ($openTickets->take(5) as $ticket) {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        "Close Ticket #{$ticket->id}",
                        callback_data: "close_ticket:{$ticket->id}"
                    )
                );
            }
        }

        $bot->sendMessage(
            text: $message,
            parse_mode: 'HTML',
            reply_markup: $keyboard->toArray()['inline_keyboard'] ? $keyboard : null
        );
    }
}
