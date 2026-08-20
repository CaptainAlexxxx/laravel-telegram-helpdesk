<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotLocale extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get resources for this locale
     */
    public function resources(): HasMany
    {
        return $this->hasMany(BotResource::class, 'locale_id');
    }

    /**
     * Get users with this locale
     */
    public function users(): HasMany
    {
        return $this->hasMany(TelegramUser::class, 'locale_id');
    }

    /**
     * Get default locale
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->first();
    }
}
