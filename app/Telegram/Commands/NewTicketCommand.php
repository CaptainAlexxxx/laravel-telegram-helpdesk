<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\TemplateService;
use App\Services\TicketService;
use SergiX44\Nutgram\Nutgram;

class NewTicketCommand extends BaseCommand
{
    protected string $command = 'new_ticket';

    protected ?string $description = 'Create a new support ticket';

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

        // Check if user already has an active ticket
        $activeTicket = $ticketService->getUserActiveTicket($user);

        if ($activeTicket) {
            $bot->sendMessage(
                text: 'You already have an active ticket. Please describe your issue or close the current ticket first.',
                parse_mode: 'HTML'
            );

            return;
        }

        // Prompt user to describe the issue
        $message = $templateService->getTemplate('messages.describe_issue', $user->locale_id)
            ?? 'Please describe your issue in the next message:';

        $bot->sendMessage(
            text: $message,
            parse_mode: 'HTML'
        );
    }
}
