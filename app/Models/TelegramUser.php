<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'role',
        'locale_id',
        'forum_topic_id',
        'forum_topic_chat_id',
    ];

    /**
     * Get the locale for this user
     */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(BotLocale::class, 'locale_id');
    }

    /**
     * Get tickets created by this user
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    /**
     * Get logs for this user
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BotLog::class, 'user_id');
    }

    /**
     * Get user full name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Check if user is agent
     */
    public function isAgent(): bool
    {
        return in_array($this->role, ['agent', 'admin']);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is an employee (requires project selection on ticket creation).
     * Employees are distinct from agents/admins.
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Find or create user by Telegram data
     */
    public static function findOrCreateByTelegramId(int $telegramId, array $data = []): self
    {
        $role = 'client';
        if (in_array($telegramId, config('bot.employees', []))) {
            $role = 'employee';
        }

        return self::firstOrCreate(
            ['telegram_id' => $telegramId],
            array_merge([
                'role' => $role,
                'locale_id' => BotLocale::getDefault()?->id,
            ], $data)
        );
    }
}
