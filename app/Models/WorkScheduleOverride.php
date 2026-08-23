<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WorkScheduleOverride extends Model
{
    protected $fillable = [
        'date',
        'is_working_day',
        'start_time',
        'end_time',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'is_working_day' => 'boolean',
    ];

    /**
     * Get override for specific date
     */
    public static function getForDate(Carbon $date): ?self
    {
        return self::whereDate('date', $date->toDateString())->first();
    }

    /**
     * Get upcoming overrides
     */
    public static function getUpcoming(int $limit = 10)
    {
        return self::whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }
}
