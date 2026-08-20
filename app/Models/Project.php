<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get tickets belonging to this project
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'project_id');
    }

    /**
     * Scope: only active (visible) projects
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all active projects ordered by sort_order for button display
     */
    public static function getActive(): Collection
    {
        return self::active()->orderBy('sort_order')->orderBy('id')->get();
    }
}
