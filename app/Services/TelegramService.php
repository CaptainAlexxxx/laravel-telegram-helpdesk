<?php

namespace App\Services;

use App\Jobs\AutoCloseTicketJob;
use App\Jobs\ReminderAgentsJob;
use App\Models\Ticket;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;

    protected string $apiUrl;

    public function __construct()
    {
        $this->token = config('nutgram.token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Create forum topic
     */
    public function createForumTopic(int $chatId, string $name, ?int $iconColor = null): ?array
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'name' => $name,
            ];

            if ($iconColor) {
                $params['icon_color'] = $iconColor;
            }

            return $this->makeRequestWithRetry('createForumTopic', $params);
        } catch (\Exception $e) {
            Log::error('Exception creating forum topic', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'name' => $name,
            ]);

            return null;
        }
    }

    /**
     * Close forum topic
     */
    public function closeForumTopic(int $chatId, int $messageThreadId): bool
    {
        try {
            $result = $this->makeRequestWithRetry('closeForumTopic', [
                'chat_id' => $chatId,
                'message_thread_id' => $messageThreadId,
            ]);

            return $result !== null;
        } catch (\Exception $e) {
            Log::error('Exception closing forum topic', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Edit forum topic
     */
    public function editForumTopic(int $chatId, int $messageThreadId, string $name): bool
    {
        try {
            $result = $this->makeRequestWithRetry('editForumTopic', [
                'chat_id' => $chatId,
                'message_thread_id' => $messageThreadId,
                'name' => $name,
            ]);

            return $result !== null;
        } catch (\Exception $e) {
            Log::error('Exception editing forum topic', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send message to topic (handles long messages)
     */
    public function sendMessageToTopic(
        int $chatId,
        int $messageThreadId,
        string $text,
        ?array $replyMarkup = null
    ): ?array {
        try {
            $splitter = app(MessageSplitter::class);

            // If message is short, send normally
            if (! $splitter->needsSplitting($text)) {
                $params = [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ];

                if ($replyMarkup) {
                    $params['reply_markup'] = json_encode($replyMarkup);
                }

                return $this->makeRequestWithRetry('sendMessage', $params);
            }

            // Split long messages
            $chunks = $splitter->splitMessage($text);
            $lastResult = null;

            foreach ($chunks as $index => $chunk) {
                $params = [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                    'text' => $chunk,
                    'parse_mode' => 'HTML',
                ];

                // Add keyboard only to last chunk
                if ($replyMarkup && $index === count($chunks) - 1) {
                    $params['reply_markup'] = json_encode($replyMarkup);
                }

                $lastResult = $this->makeRequestWithRetry('sendMessage', $params);

                // Small delay between chunks
                if ($index < count($chunks) - 1) {
                    usleep(100000); // 100ms
                }
            }

            return $lastResult;

        } catch (\Exception $e) {
            Log::error('Exception sending message to topic', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Copy message to topic
     */
    public function copyMessageToTopic(
        int $toChatId,
        int $fromChatId,
        int $messageId,
        int $messageThreadId
    ): ?array {
        try {
            return $this->makeRequestWithRetry('copyMessage', [
                'chat_id' => $toChatId,
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
                'message_thread_id' => $messageThreadId,
            ]);
        } catch (\Exception $e) {
            Log::error('Exception copying message', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send message with inline keyboard
     */
    public function sendMessageWithKeyboard(int $chatId, string $text, array $buttons): ?array
    {
        try {
            $keyboard = ['inline_keyboard' => $buttons];

            return $this->makeRequestWithRetry('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard),
            ]);
        } catch (\Exception $e) {
            Log::error('Exception sending message with keyboard', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Edit message reply markup
     */
    public function editMessageReplyMarkup(
        int $chatId,
        int $messageId,
        ?array $replyMarkup = null
    ): bool {
        try {
            $params = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ];

            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            $result = $this->makeRequestWithRetry('editMessageReplyMarkup', $params);

            return $result !== null;
        } catch (\Exception $e) {
            Log::error('Exception editing message markup', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Schedule reminder job for agents
     */
    public function scheduleReminderJob(Ticket $ticket): void
    {
        // Non-working hours are covered by after-hours reminders, skip agent reminder
        if (! app(WorkScheduleService::class)->isWorkingHoursNow()) {
            Log::info('Reminder job skipped: non-working hours', ['ticket_id' => $ticket->id]);

            return;
        }

        // Cancel existing reminder if any
        $this->cancelReminderJob($ticket);

        $delayMinutes = config('bot.timers.reminder_to_agents', 60);

        $job = ReminderAgentsJob::dispatch($ticket)
            ->delay(now()->addMinutes($delayMinutes));

        // Store job ID (Laravel doesn't provide job ID directly, so we use unique identifier)
        $ticket->update([
            'reminder_job_id' => 'reminder_'.$ticket->id.'_'.now()->timestamp,
        ]);

        Log::info('Scheduled reminder job', [
            'ticket_id' => $ticket->id,
            'delay_minutes' => $delayMinutes,
        ]);
    }

    /**
     * Cancel reminder job
     */
    public function cancelReminderJob(Ticket $ticket): void
    {
        if ($ticket->reminder_job_id) {
            // Note: Laravel doesn't provide easy way to cancel specific delayed job
            // The job itself will check ticket status and skip if needed
            $ticket->update(['reminder_job_id' => null]);

            Log::info('Cancelled reminder job', [
                'ticket_id' => $ticket->id,
            ]);
        }
    }

    /**
     * Schedule auto-close job for ticket
     */
    public function scheduleAutoCloseJob(Ticket $ticket): void
    {
        // Cancel existing auto-close job if any
        $this->cancelAutoCloseJob($ticket);

        $delayMinutes = config('bot.timers.auto_close_ticket', 15);

        $job = AutoCloseTicketJob::dispatch($ticket)
            ->delay(now()->addMinutes($delayMinutes));

        $ticket->update([
            'auto_close_job_id' => 'auto_close_'.$ticket->id.'_'.now()->timestamp,
        ]);

        Log::info('Scheduled auto-close job', [
            'ticket_id' => $ticket->id,
            'delay_minutes' => $delayMinutes,
        ]);
    }

    /**
     * Cancel auto-close job
     */
    public function cancelAutoCloseJob(Ticket $ticket): void
    {
        if ($ticket->auto_close_job_id) {
            $ticket->update(['auto_close_job_id' => null]);

            Log::info('Cancelled auto-close job', [
                'ticket_id' => $ticket->id,
            ]);
        }
    }

    /**
     * Reset agent waiting timer
     */
    public function resetAgentWaitingTimer(Ticket $ticket): void
    {
        $ticket->update([
            'last_agent_message_at' => now(),
        ]);

        // Cancel reminder job (agent replied)
        $this->cancelReminderJob($ticket);

        // Schedule auto-close job
        $this->scheduleAutoCloseJob($ticket);

        Log::info('Reset agent waiting timer and scheduled auto-close', [
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Copy message to client
     */
    public function copyMessageToClient(
        int $toChatId,
        int $fromChatId,
        int $messageId
    ): ?array {
        try {
            $response = Http::post("{$this->apiUrl}/copyMessage", [
                'chat_id' => $toChatId,
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
            ]);

            if ($response->successful()) {
                return $response->json('result');
            }

            Log::error('Failed to copy message to client', [
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception copying message to client', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Make HTTP request with retry logic
     */
    protected function makeRequestWithRetry(string $method, array $params, int $maxRetries = 3): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(30)
                    ->retry(3, 100, function ($exception, $request) {
                        // Retry on 429 (rate limit) and 5xx errors
                        if ($exception instanceof RequestException) {
                            $status = $exception->response?->status();

                            return $status === 429 || ($status >= 500 && $status < 600);
                        }

                        return false;
                    })
                    ->post("{$this->apiUrl}/{$method}", $params);

                if ($response->successful()) {
                    $result = $response->json('result');

                    // Telegram API sometimes returns boolean true for successful operations
                    // like pinChatMessage, closeForumTopic, etc.
                    return $result;
                }

                // Handle rate limit (429)
                if ($response->status() === 429) {
                    $retryAfter = $response->json('parameters.retry_after', 5);
                    Log::warning("Telegram API rate limit, retry after {$retryAfter}s", [
                        'method' => $method,
                        'attempt' => $attempt + 1,
                    ]);
                    sleep($retryAfter);
                    $attempt++;

                    continue;
                }

                // Handle server errors (5xx)
                if ($response->status() >= 500) {
                    Log::warning("Telegram API server error: {$response->status()}", [
                        'method' => $method,
                        'attempt' => $attempt + 1,
                    ]);
                    sleep(2 ** $attempt); // Exponential backoff
                    $attempt++;

                    continue;
                }

                // Other errors
                Log::error('Telegram API request failed', [
                    'method' => $method,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return null;

            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Telegram API request exception', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);

                if ($attempt < $maxRetries - 1) {
                    sleep(2 ** $attempt); // Exponential backoff
                }
                $attempt++;
            }
        }

        // All retries failed
        Log::error('Telegram API: all retries exhausted', [
            'method' => $method,
            'last_exception' => $lastException?->getMessage(),
        ]);

        return null;
    }

    /**
     * Send photo to user by Telegram file_id
     */
    public function sendPhotoToUser(int $chatId, string $fileId, ?string $caption = null, ?array $replyMarkup = null): ?array
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'photo' => $fileId,
                'parse_mode' => 'HTML',
            ];

            if ($caption) {
                $params['caption'] = $caption;
            }

            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            return $this->makeRequestWithRetry('sendPhoto', $params);
        } catch (\Exception $e) {
            Log::error('Failed to send photo to user', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send message to user (avoids context issues)
     */
    public function sendMessageToUser(int $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];

            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            return $this->makeRequestWithRetry('sendMessage', $params);
        } catch (\Exception $e) {
            Log::error('Failed to send message to user', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set a reaction on a message
     */
    public function setMessageReaction(int $chatId, int $messageId, string $emoji): bool
    {
        try {
            $result = $this->makeRequestWithRetry('setMessageReaction', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
            ]);

            return $result !== null;
        } catch (\Exception $e) {
            Log::error('Exception setting message reaction', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'emoji' => $emoji,
            ]);

            return false;
        }
    }

    /**
     * Add "forwarded to client" reaction to the original agent message.
     * Reads emoji from config('bot.forwarded_to_client_reaction').
     * Silently skips if the value is empty.
     */
    public function addForwardedToClientReaction(int $chatId, int $messageId): void
    {
        $emoji = config('bot.forwarded_to_client_reaction', '');

        if (empty($emoji)) {
            return;
        }

        try {
            $this->setMessageReaction($chatId, $messageId, $emoji);
        } catch (\Exception $e) {
            // Non-critical: reaction failure must not affect the forwarding flow
            Log::warning('Failed to add forwarded-to-client reaction', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        }
    }

    /**
     * Pin message in chat
     */
    public function pinChatMessage(int $chatId, int $messageId, int $messageThreadId): bool
    {
        try {
            $result = $this->makeRequestWithRetry('pinChatMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'message_thread_id' => $messageThreadId,
            ]);

            // makeRequestWithRetry returns ?array, so check for non-null
            if ($result !== null) {
                Log::info('Message pinned successfully', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'message_thread_id' => $messageThreadId,
                ]);

                return true;
            }

            Log::warning('Failed to pin message (null result)', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception pinning message', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return false;
        }
    }
}
