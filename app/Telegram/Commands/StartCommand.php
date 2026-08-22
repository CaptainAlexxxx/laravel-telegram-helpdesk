<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\TemplateService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class StartCommand extends BaseCommand
{
    protected string $command = 'start';

    protected ?string $description = 'Start the bot';

    public function handle(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $userData = $bot->user();

        // Find or create user
        $user = TelegramUser::findOrCreateByTelegramId($telegramId, [
            'username' => $userData->username,
            'first_name' => $userData->first_name,
            'last_name' => $userData->last_name,
        ]);

        // Log command execution
        $this->logCommand($bot, $user);

        // Get welcome message from templates
        $templateService = app(TemplateService::class);
        $welcomeMessage = $templateService->getTemplateWithVars(
            'messages.welcome',
            ['username' => $user->first_name ?? 'User'],
            $user->locale_id
        );

        Log::info('Sending welcome message...');
        // Send welcome message
        $bot->sendMessage(
            text: $welcomeMessage ?? 'Welcome to support bot!',
            parse_mode: 'HTML'
        );
        Log::info('Welcome message sent!');
    }
}
