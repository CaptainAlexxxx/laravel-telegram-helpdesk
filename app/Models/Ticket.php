<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'subject',
        'status',
        'forum_topic_id',
        'service_chat_id',
        'pinned_message_id',
        'reminder_job_id',
        'auto_close_job_id',
        'last_client_message_at',
        'last_agent_message_at',
        'first_reply_at',
        'closed_at',
        'first_agent_id',
        'last_agent_id',
        'review_rating',
        'review_comment',
    ];

    protected $casts = [
        'first_reply_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_client_message_at' => 'datetime',
        'last_agent_message_at' => 'datetime',
    ];

    /**
     * Get the project this ticket belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the user who created this ticket
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }

    /**
     * Get the first agent who replied
     */
    public function firstAgent(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'first_agent_id');
    }

    /**
     * Get the last agent who replied
     */
    public function lastAgent(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'last_agent_id');
    }

    /**
     * Get logs for this ticket
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BotLog::class, 'ticket_id');
    }

    /**
     * Check if ticket is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Close the ticket
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Get response time in minutes
     */
    public function getResponseTimeAttribute(): ?int
    {
        if (! $this->first_reply_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->first_reply_at);
    }

    /**
     * Get resolution time in minutes
     */
    public function getResolutionTimeAttribute(): ?int
    {
        if (! $this->closed_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->closed_at);
    }
}
