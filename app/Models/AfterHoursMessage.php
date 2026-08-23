<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfterHoursMessage extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'ticket_id',
        'period_start',
        'auto_reply_sent',
        'reminder_needed',
        'reminder_sent',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'auto_reply_sent' => 'boolean',
        'reminder_needed' => 'boolean',
        'reminder_sent' => 'boolean',
    ];

    /**
     * Get the telegram user
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id');
    }

    /**
     * Get the ticket
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Get record for user in specific non-working period
     */
    public static function getForUserInPeriod(int $userId, Carbon $periodStart): ?self
    {
        return self::where('telegram_user_id', $userId)
            ->where('period_start', $periodStart)
            ->first();
    }

    /**
     * Get pending reminders (need to be sent when work hours start)
     */
    public static function getPendingReminders()
    {
        return self::where('reminder_needed', true)
            ->where('reminder_sent', false)
            ->with(['telegramUser', 'ticket'])
            ->get();
    }
}
