<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\TemplateService;
use App\Services\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AutoCloseTicketJob implements ShouldQueue
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
    public function handle(TicketService $ticketService, TemplateService $templateService, Nutgram $bot): void
    {
        // Reload ticket to check current status
        $this->ticket->refresh();

        // If ticket is already closed, do nothing
        if ($this->ticket->isClosed()) {
            Log::info('AutoCloseTicketJob skipped: ticket already closed', [
                'ticket_id' => $this->ticket->id,
            ]);

            return;
        }

        // Check if there were new messages from client after scheduling
        if ($this->ticket->last_client_message_at &&
            $this->ticket->last_agent_message_at &&
            $this->ticket->last_client_message_at > $this->ticket->last_agent_message_at) {
            Log::info('AutoCloseTicketJob skipped: client sent new message', [
                'ticket_id' => $this->ticket->id,
            ]);

            return;
        }

        // Close the ticket
        $ticketService->closeTicket($this->ticket);

        $user = $this->ticket->user;

        // Send notification to client
        $closeMessage = $templateService->getTemplate('messages.ticket_auto_closed', $user->locale_id)
            ?? 'Your ticket has been automatically closed due to inactivity. If you still need help, please send a new message.';

        try {
            $bot->sendMessage(
                text: $closeMessage,
                chat_id: $user->telegram_id,
                parse_mode: 'HTML'
            );

            // Ask for rating
            $this->askForRating($bot, $user, $templateService);

            Log::info('AutoCloseTicketJob executed', [
                'ticket_id' => $this->ticket->id,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('AutoCloseTicketJob: failed to send notification', [
                'ticket_id' => $this->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear auto-close job ID
        $this->ticket->update(['auto_close_job_id' => null]);
    }

    /**
     * Ask user to rate the support
     */
    protected function askForRating(Nutgram $bot, $user, TemplateService $templateService): void
    {
        $ratingMessage = $templateService->getTemplate('messages.rate_request', $user->locale_id)
            ?? 'Please rate the quality of support from 1 to 5:';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('1', callback_data: "rate:{$this->ticket->id}:1"),
                InlineKeyboardButton::make('2', callback_data: "rate:{$this->ticket->id}:2"),
                InlineKeyboardButton::make('3', callback_data: "rate:{$this->ticket->id}:3"),
            )
            ->addRow(
                InlineKeyboardButton::make('4', callback_data: "rate:{$this->ticket->id}:4"),
                InlineKeyboardButton::make('5', callback_data: "rate:{$this->ticket->id}:5"),
            );

        $bot->sendMessage(
            text: $ratingMessage,
            chat_id: $user->telegram_id,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Get the unique ID for the job
     */
    public function uniqueId(): string
    {
        return 'auto_close_ticket_'.$this->ticket->id;
    }
}
