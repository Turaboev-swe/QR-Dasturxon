<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-restaurant, per-day aggregate row. Never written to directly by
 * controllers — see App\Services\DailyStatsService, which is the single
 * place that increments these counters (CLAUDE.md thin-controller rule).
 */
#[Fillable(['restaurant_id', 'date', 'orders_count', 'orders_total_amount', 'waiter_calls_count', 'bill_requests_count', 'unique_users_count', 'reviews_count'])]
class DailyStat extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
