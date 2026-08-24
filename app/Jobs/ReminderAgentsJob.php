<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\TelegramService;
use App\Services\WorkScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReminderAgentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Ticket $ticket;

    /**
     * Create a new job instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService, WorkScheduleService $scheduleService): void
    {
        // Reload ticket to check current status
        $this->ticket->refresh();

        // If ticket is closed or already has reply, do nothing
        if ($this->ticket->isClosed() || $this->ticket->first_reply_at) {
            Log::info('ReminderAgentsJob skipped', [
                'ticket_id' => $this->ticket->id,
                'status' => $this->ticket->status,
                'has_reply' => (bool) $this->ticket->first_reply_at,
            ]);

            return;
        }

        // Agents are offline — defer reminder to next working time
        if (! $scheduleService->isWorkingHoursNow()) {
            $nextWorkingTime = $scheduleService->getNextWorkingTime();

            if ($nextWorkingTime) {
                self::dispatch($this->ticket)->delay($nextWorkingTime);

                Log::info('ReminderAgentsJob deferred to working hours', [
                    'ticket_id' => $this->ticket->id,
                    'next_working_time' => $nextWorkingTime->toDateTimeString(),
                ]);
            }

            return;
        }

        // Check if ticket still has no reply from agents
        if (! $this->ticket->forum_topic_id || ! $this->ticket->service_chat_id) {
            Log::warning('ReminderAgentsJob: ticket has no forum topic', [
                'ticket_id' => $this->ticket->id,
            ]);

            return;
        }

        // Send reminder to service chat
        $user = $this->ticket->user;
        $waitingTime = $this->ticket->created_at->diffForHumans();

        $reminderText = "REMINDER: Client is waiting for response!\n\n".
            "Ticket: #{$this->ticket->id}\n".
            "Client: {$user->full_name}\n".
            "Telegram ID: {$user->telegram_id}\n".
            ($user->username ? "Username: @{$user->username}\n" : '').
            "Waiting time: {$waitingTime}\n\n".
            "Issue:\n{$this->ticket->subject}";

        $telegramService->sendMessageToTopic(
            $this->ticket->service_chat_id,
            $this->ticket->forum_topic_id,
            $reminderText
        );

        Log::info('ReminderAgentsJob executed', [
            'ticket_id' => $this->ticket->id,
            'waiting_time' => $waitingTime,
        ]);

        // Clear reminder job ID
        $this->ticket->update(['reminder_job_id' => null]);
    }

    /**
     * Get the unique ID for the job
     */
    public function uniqueId(): string
    {
        return 'reminder_agents_'.$this->ticket->id;
    }
}
