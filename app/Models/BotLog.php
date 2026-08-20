<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotLog extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'event_type',
        'event_source',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the user for this log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }

    /**
     * Get the ticket for this log
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Log an event
     */
    public static function logEvent(
        string $eventType,
        string $eventSource,
        ?int $userId = null,
        ?int $ticketId = null,
        ?array $payload = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'payload' => $payload,
        ]);
    }
}
