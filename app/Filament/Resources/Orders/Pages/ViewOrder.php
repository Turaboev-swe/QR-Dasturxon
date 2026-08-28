<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

// Read-only — no Edit action; see OrderResource::canEdit().
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
