<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class ProcessedUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'update_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Atomically reserve an update_id; false means another worker already took it.
     */
    public static function claim(int $updateId): bool
    {
        try {
            self::create([
                'update_id' => $updateId,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * Clean old processed updates (older than 7 days)
     */
    public static function cleanOld(): int
    {
        return self::where('processed_at', '<', now()->subDays(7))->delete();
    }
}
