<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class NotificationService
{
    public function __construct(
        protected Nutgram $bot,
        protected TemplateService $templateService
    ) {}

    /**
     * Notify client about processing error
     */
    public function notifyClientAboutError(int $chatId, ?int $localeId = null): void
    {
        try {
            $errorMessage = $this->templateService->getTemplate('messages.processing_error', $localeId)
                ?? 'Sorry, there was a problem processing your request. Please try again in a few moments.';

            $this->bot->sendMessage(
                text: $errorMessage,
                chat_id: $chatId,
                parse_mode: 'HTML'
            );
        } catch (\Exception $e) {
            Log::error('Failed to send error notification to client', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
