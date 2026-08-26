<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'restaurant_table_id', 'telegram_user_id', 'status', 'type', 'handled_by_telegram_id', 'handled_by_name'])]
class WaiterCall extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'handled_by_telegram_id' => 'integer',
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACKNOWLEDGED, self::STATUS_RESOLVED];

    public const TYPE_WAITER = 'waiter';

    public const TYPE_BILL = 'bill';

    public const TYPES = [self::TYPE_WAITER, self::TYPE_BILL];

    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_ACKNOWLEDGED, self::STATUS_RESOLVED],
        self::STATUS_ACKNOWLEDGED => [self::STATUS_RESOLVED],
        self::STATUS_RESOLVED => [],
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }
}
