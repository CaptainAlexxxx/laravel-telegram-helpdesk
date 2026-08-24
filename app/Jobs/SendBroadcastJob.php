<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\TelegramUser;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Broadcast $broadcast;

    public function __construct(Broadcast $broadcast)
    {
        $this->broadcast = $broadcast;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService): void
    {
        // Reload to get fresh status
        $this->broadcast->refresh();

        if ($this->broadcast->status !== 'pending') {
            Log::info('SendBroadcastJob skipped: broadcast is not pending', [
                'broadcast_id' => $this->broadcast->id,
                'status' => $this->broadcast->status,
            ]);

            return;
        }

        // Get target users by role
        $query = TelegramUser::query();

        if ($this->broadcast->role !== 'all') {
            $query->where('role', $this->broadcast->role);
        }

        $users = $query->get();

        Log::info('SendBroadcastJob started', [
            'broadcast_id' => $this->broadcast->id,
            'role' => $this->broadcast->role,
            'users_count' => $users->count(),
        ]);

        $sent = 0;
        $fails = 0;

        foreach ($users as $user) {
            try {
                if ($this->broadcast->photo_file_id) {
                    $result = $telegramService->sendPhotoToUser(
                        $user->telegram_id,
                        $this->broadcast->photo_file_id,
                        $this->broadcast->caption
                    );
                } else {
                    $result = $telegramService->sendMessageToUser(
                        $user->telegram_id,
                        $this->broadcast->message_text
                    );
                }

                if ($result) {
                    $sent++;
                } else {
                    $fails++;
                    Log::warning('SendBroadcastJob: failed to send to user', [
                        'broadcast_id' => $this->broadcast->id,
                        'user_id' => $user->id,
                        'telegram_id' => $user->telegram_id,
                    ]);
                }

                // Small delay to avoid Telegram rate limits (30 msg/sec limit)
                usleep(50000); // 50ms

            } catch (\Exception $e) {
                $fails++;
                Log::error('SendBroadcastJob: exception sending to user', [
                    'broadcast_id' => $this->broadcast->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark as sent
        $this->broadcast->update(['status' => 'sent']);

        Log::info('SendBroadcastJob completed', [
            'broadcast_id' => $this->broadcast->id,
            'sent' => $sent,
            'fails' => $fails,
        ]);
    }

    /**
     * Unique job ID to prevent duplicates
     */
    public function uniqueId(): string
    {
        return 'broadcast_'.$this->broadcast->id;
    }
}
