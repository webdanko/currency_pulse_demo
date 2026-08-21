<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $fillable = [
        'quote',
        'operator',
        'threshold',
        'active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Does the given rate cross this rule's threshold? */
    public function isCrossedBy(float $rate): bool
    {
        return $this->operator === 'above'
            ? $rate > $this->threshold
            : $rate < $this->threshold;
    }
}
