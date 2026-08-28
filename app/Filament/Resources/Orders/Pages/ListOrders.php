<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

// Read-only resource (buyurtmalar tarixiy moliyaviy yozuv) — no "New order"
// header action; see OrderResource::canCreate().
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
