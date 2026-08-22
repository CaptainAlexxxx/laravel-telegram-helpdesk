<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\TemplateService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class FaqCommand extends BaseCommand
{
    protected string $command = 'faq';

    protected ?string $description = 'Show frequently asked questions';

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

        // Get FAQ header message
        $faqHeader = $templateService->getTemplate('messages.faq_header', $user->locale_id)
            ?? 'Frequently Asked Questions:\n\nSelect a question to see the answer:';

        // Create FAQ buttons
        $keyboard = InlineKeyboardMarkup::make();

        // Get FAQ count from resources (faq_question_1, faq_question_2, etc.)
        for ($i = 1; $i <= 10; $i++) {
            $questionKey = "faq.question_{$i}";
            $question = $templateService->getTemplate($questionKey, $user->locale_id);

            if (! $question) {
                break; // No more FAQ items
            }

            $keyboard->addRow(
                InlineKeyboardButton::make($question, callback_data: "faq:{$i}")
            );
        }

        // Add "Back to menu" button if needed
        $keyboard->addRow(
            InlineKeyboardButton::make(
                $templateService->getTemplate('button.back_to_menu', $user->locale_id) ?? 'Back to menu',
                callback_data: 'menu:main'
            )
        );

        $bot->sendMessage(
            text: $faqHeader,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }
}
