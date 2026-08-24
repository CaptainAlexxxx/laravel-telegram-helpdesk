<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\ConversationService;
use App\Services\TemplateService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class CreateBroadcastCommand extends BaseCommand
{
    protected string $command = 'mmsg';

    protected ?string $description = 'Create a broadcast message (admin only)';

    public function handle(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        $templateService = app(TemplateService::class);

        if (! $user) {
            $bot->sendMessage(text: $templateService->getTemplate('messages.start_first', null) ?? 'Please start the bot first using /start');

            return;
        }

        if (! $user->isAdmin()) {
            $bot->sendMessage(text: $templateService->getTemplate('messages.access_denied_admin', $user->locale_id) ?? 'Access denied. Admin only.');

            return;
        }

        $this->logCommand($bot, $user);

        // Reset any active state before starting a new broadcast
        $conversationService = app(ConversationService::class);
        $conversationService->clearState($user);
        $conversationService->setState($user, 'mmsg_select_role', [], 60);

        $locale = $user->locale_id;

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_all', $locale) ?? 'All', callback_data: 'mmsg:role:all'),
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_clients', $locale) ?? 'Clients', callback_data: 'mmsg:role:client'),
            )
            ->addRow(
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_employees', $locale) ?? 'Employees', callback_data: 'mmsg:role:employee'),
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_agents', $locale) ?? 'Agents', callback_data: 'mmsg:role:agent'),
            )
            ->addRow(
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_admins', $locale) ?? 'Admins', callback_data: 'mmsg:role:admin'),
            )
            ->addRow(
                InlineKeyboardButton::make($templateService->getTemplate('button.mmsg_cancel', $locale) ?? 'Cancel', callback_data: 'mmsg:cancel'),
            );

        $bot->sendMessage(
            text: $templateService->getTemplate('messages.mmsg_select_role', $locale) ?? 'New broadcast. Step 1/3. Choose recipients:',
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }
}
