<?php

namespace App\Services;

use App\Models\BotLog;
use App\Models\TelegramUser;
use App\Models\Ticket;

class TicketService
{
    /**
     * Create a new ticket
     */
    public function createTicket(TelegramUser $user, string $subject): Ticket
    {
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => $subject,
            'status' => 'open',
        ]);

        // Log ticket creation
        BotLog::logEvent(
            eventType: 'ticket',
            eventSource: 'telegram_user',
            userId: $user->id,
            ticketId: $ticket->id,
            payload: ['action' => 'created', 'subject' => $subject]
        );

        return $ticket;
    }

    /**
     * Get user's active ticket
     */
    public function getUserActiveTicket(TelegramUser $user): ?Ticket
    {
        return Ticket::where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->latest()
            ->first();
    }

    /**
     * Resolve current ticket for a forum topic.
     * Topics are permanent per user and shared by many tickets — prefer the
     * open one, fall back to the most recent.
     */
    public function getTicketByForumTopic(int $forumTopicId, int $serviceChatId): ?Ticket
    {
        $base = Ticket::where('forum_topic_id', $forumTopicId)
            ->where('service_chat_id', $serviceChatId);

        return (clone $base)->where('status', '!=', 'closed')->latest()->first()
            ?? (clone $base)->latest()->first();
    }

    /**
     * Close ticket
     */
    public function closeTicket(Ticket $ticket, ?int $agentId = null): void
    {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        if ($agentId) {
            $ticket->update(['last_agent_id' => $agentId]);
        }

        // Log ticket closure
        BotLog::logEvent(
            eventType: 'status_change',
            eventSource: $agentId ? 'telegram_agent' : 'telegram_user',
            userId: $ticket->user_id,
            ticketId: $ticket->id,
            payload: ['action' => 'closed', 'agent_id' => $agentId]
        );
    }

    /**
     * Add first agent reply
     */
    public function addFirstReply(Ticket $ticket, int $agentId): void
    {
        if ($ticket->first_reply_at) {
            return; // Already has first reply
        }

        $ticket->update([
            'first_reply_at' => now(),
            'first_agent_id' => $agentId,
            'last_agent_id' => $agentId,
        ]);

        // Log first reply
        BotLog::logEvent(
            eventType: 'message',
            eventSource: 'telegram_agent',
            userId: $ticket->user_id,
            ticketId: $ticket->id,
            payload: ['action' => 'first_reply', 'agent_id' => $agentId]
        );
    }

    /**
     * Update last agent
     */
    public function updateLastAgent(Ticket $ticket, int $agentId): void
    {
        $ticket->update(['last_agent_id' => $agentId]);
    }

    /**
     * Add review to ticket
     */
    public function addReview(Ticket $ticket, int $rating, ?string $comment = null): void
    {
        $ticket->update([
            'review_rating' => $rating,
            'review_comment' => $comment,
        ]);

        // Log review
        BotLog::logEvent(
            eventType: 'rating',
            eventSource: 'telegram_user',
            userId: $ticket->user_id,
            ticketId: $ticket->id,
            payload: ['rating' => $rating, 'comment' => $comment]
        );
    }

    /**
     * Update forum topic info
     */
    public function updateForumTopic(Ticket $ticket, int $topicId, int $chatId): void
    {
        $ticket->update([
            'forum_topic_id' => $topicId,
            'service_chat_id' => $chatId,
        ]);
    }

    /**
     * Get ticket statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Ticket::query();

        // Apply filters
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $tickets = $query->get();

        return [
            'total' => $tickets->count(),
            'open' => $tickets->where('status', 'open')->count(),
            'closed' => $tickets->where('status', 'closed')->count(),
            'avg_response_time' => $tickets->whereNotNull('first_reply_at')
                ->avg(fn ($t) => $t->response_time),
            'avg_resolution_time' => $tickets->whereNotNull('closed_at')
                ->avg(fn ($t) => $t->resolution_time),
            'avg_rating' => $tickets->whereNotNull('review_rating')
                ->avg('review_rating'),
        ];
    }
}
