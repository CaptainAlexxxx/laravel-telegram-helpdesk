<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $fillable = [
        'created_by',
        'role',
        'message_text',
        'photo_file_id',
        'caption',
        'scheduled_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Scope: only pending broadcasts scheduled in the future
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '>=', now());
    }

    /**
     * Fallback role label used when no localized template exists.
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'all' => 'Everyone',
            'client' => 'Clients',
            'employee' => 'Employees',
            'agent' => 'Agents',
            'admin' => 'Admins',
            default => $this->role,
        };
    }

    /**
     * Get preview text (message_text or caption), truncated to 100 chars
     */
    public function getPreviewTextAttribute(): string
    {
        $text = $this->message_text ?? $this->caption ?? '—';

        return mb_strlen($text) > 100
            ? mb_substr($text, 0, 100).'...'
            : $text;
    }
}
