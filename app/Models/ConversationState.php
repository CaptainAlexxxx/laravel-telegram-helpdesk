<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationState extends Model
{
    protected $fillable = [
        'user_id',
        'state',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user for this conversation state
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'user_id');
    }

    /**
     * Check if state is expired
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get or create state for user
     */
    public static function getForUser(int $userId): ?self
    {
        $state = self::where('user_id', $userId)->first();

        if ($state && $state->isExpired()) {
            $state->delete();

            return null;
        }

        return $state;
    }

    /**
     * Set state for user
     */
    public static function setForUser(int $userId, string $state, ?array $data = null, ?int $expiresInMinutes = 30): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            [
                'state' => $state,
                'data' => $data,
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ]
        );
    }

    /**
     * Clear state for user
     */
    public static function clearForUser(int $userId): void
    {
        self::where('user_id', $userId)->delete();
    }
}
