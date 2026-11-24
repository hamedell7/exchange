<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MarketExchangeRate extends Model
{
    protected $fillable = [
        'base_currency', 'quote_currency', 'rate', 'provider', 'provider_pair', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:12',
            'fetched_at' => 'immutable_datetime',
        ];
    }
}
