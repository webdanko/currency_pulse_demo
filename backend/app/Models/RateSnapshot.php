<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RateSnapshot extends Model
{
    protected $fillable = [
        'base',
        'quote',
        'rate',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    /** Latest snapshot per quote currency. */
    public function scopeForQuote(Builder $query, string $quote): Builder
    {
        return $query->where('quote', strtoupper($quote));
    }

    public function scopeSince(Builder $query, \DateTimeInterface $since): Builder
    {
        return $query->where('captured_at', '>=', $since);
    }
}
