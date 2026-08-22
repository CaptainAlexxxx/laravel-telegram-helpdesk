<?php

namespace App\Telegram\Commands;

use App\Models\BotLog;
use App\Models\TelegramUser;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

abstract class BaseCommand extends Command
{
    /**
     * Log command execution
     */
    protected function logCommand(Nutgram $bot, ?TelegramUser $user = null, ?array $additionalData = []): void
    {
        if (! config('bot.logging.log_commands', true)) {
            return;
        }

        if (! $user) {
            $telegramId = $bot->userId();
            $user = TelegramUser::where('telegram_id', $telegramId)->first();
        }

        if (! $user) {
            return;
        }

        BotLog::logEvent(
            eventType: 'command',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: null,
            payload: array_merge([
                'command' => $this->command,
                'message_text' => $bot->message()?->text,
            ], $additionalData)
        );
    }
}
