<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotResource extends Model
{
    protected $fillable = [
        'type',
        'resource_key',
        'resource_value',
        'description',
        'locale_id',
    ];

    /**
     * Get the locale for this resource
     */
    public function locale(): BelongsTo
    {
        return $this->belongsTo(BotLocale::class, 'locale_id');
    }

    /**
     * Get resource by key and locale
     */
    public static function getByKey(string $key, int $localeId): ?string
    {
        return self::where('resource_key', $key)
            ->where('locale_id', $localeId)
            ->value('resource_value');
    }
}
