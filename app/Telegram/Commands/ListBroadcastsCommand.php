<?php

namespace App\Telegram\Commands;

use App\Models\Broadcast;
use App\Models\TelegramUser;
use App\Services\TemplateService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ListBroadcastsCommand extends BaseCommand
{
    protected string $command = 'listmsg';

    protected ?string $description = 'List scheduled broadcasts (admin only)';

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

        $locale = $user->locale_id;
        $broadcasts = Broadcast::pending()->orderBy('scheduled_at')->get();

        if ($broadcasts->isEmpty()) {
            $bot->sendMessage(
                text: $templateService->getTemplate('messages.mmsg_no_broadcasts', $locale) ?? 'No scheduled broadcasts.',
                parse_mode: 'HTML'
            );

            return;
        }

        foreach ($broadcasts as $broadcast) {
            $typeIcon = $broadcast->photo_file_id ? '🖼' : '📝';
            $scheduledAt = $broadcast->scheduled_at->format('d.m.Y H:i');
            $roleLabel = $templateService->getTemplate('role.'.$broadcast->role, $locale) ?? $broadcast->role_label;

            $text = "{$typeIcon} <b>Broadcast #{$broadcast->id}</b>\n"
                  ."📅 <b>Date:</b> {$scheduledAt}\n"
                  ."👥 <b>Recipients:</b> {$roleLabel}\n"
                  ."💬 <b>Message:</b> {$broadcast->preview_text}";

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        $templateService->getTemplate('button.mmsg_delete', $locale) ?? 'Delete',
                        callback_data: "broadcast:delete:{$broadcast->id}"
                    )
                );

            $bot->sendMessage(
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $keyboard
            );
        }
    }
}
