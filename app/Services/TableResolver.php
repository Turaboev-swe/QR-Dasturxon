<?php

namespace App\Services;

use App\Exceptions\InvalidQrSessionException;
use App\Models\Restaurant;
use App\Models\RestaurantTable;

class TableResolver
{
    /**
     * Resolve the restaurant and table encoded in a Mini App `start_param`
     * of the form `r{restaurant_id}_t{table_code}` (e.g. `r1_t5`).
     *
     * The table identity is never taken from user-supplied input — it is
     * always derived from the QR-provided start_param that Telegram signs
     * as part of initData.
     *
     * @return array{restaurant: Restaurant, table: RestaurantTable}
     *
     * @throws InvalidQrSessionException
     */
    public function resolve(?string $startParam): array
    {
        if (! is_string($startParam) || ! preg_match('/^r(\d+)_t(.+)$/', $startParam, $matches)) {
            throw new InvalidQrSessionException('QR sessiya parametri noto\'g\'ri yoki mavjud emas.');
        }

        [, $restaurantId, $tableCode] = $matches;

        $restaurant = Restaurant::where('id', $restaurantId)
            ->where('is_active', true)
            ->first();

        if (! $restaurant) {
            throw new InvalidQrSessionException('Restoran topilmadi.');
        }

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)
            ->where('code', $tableCode)
            ->where('is_active', true)
            ->first();

        if (! $table) {
            throw new InvalidQrSessionException('Stol topilmadi.');
        }

        return ['restaurant' => $restaurant, 'table' => $table];
    }
}
