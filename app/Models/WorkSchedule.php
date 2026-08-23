<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
        'day_of_week' => 'integer',
    ];

    /**
     * Day names mapping (ISO-8601: 1=Monday, 7=Sunday)
     */
    public const DAY_NAMES = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

    /**
     * Get schedule for specific day of week
     */
    public static function getForDay(int $dayOfWeek): ?self
    {
        return self::where('day_of_week', $dayOfWeek)->first();
    }

    /**
     * Get all schedules ordered by day
     */
    public static function getAllOrdered()
    {
        return self::orderBy('day_of_week')->get();
    }

    /**
     * Get day name by number
     */
    public static function getDayName(int $dayOfWeek): string
    {
        return self::DAY_NAMES[$dayOfWeek] ?? 'unknown';
    }
}
