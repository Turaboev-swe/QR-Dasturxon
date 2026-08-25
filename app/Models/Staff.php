<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'name', 'role', 'telegram_id', 'phone', 'is_active'])]
class Staff extends Model
{
    use HasFactory;

    public const ROLE_WAITER = 'waiter';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Cashiers manage order status directly; admins can do anything a cashier can.
     */
    public function canManageOrders(): bool
    {
        return in_array($this->role, [self::ROLE_CASHIER, self::ROLE_ADMIN], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
