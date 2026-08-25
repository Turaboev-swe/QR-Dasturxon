<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id',
    'category_id',
    'name_translations',
    'description_translations',
    'ingredients_translations',
    'allergens',
    'price',
    'image_path',
    'sort_order',
    'is_available',
    'discount_percent',
    'discount_ends_at',
    'discount_portions_total',
    'discount_portions_remaining',
    'taste_spicy',
    'taste_sweet',
    'taste_salty',
])]
class Dish extends Model
{
    use HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'ingredients_translations' => 'array',
            'allergens' => 'array',
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'discount_percent' => 'integer',
            'discount_ends_at' => 'datetime',
            'discount_portions_total' => 'integer',
            'discount_portions_remaining' => 'integer',
            'taste_spicy' => 'integer',
            'taste_sweet' => 'integer',
            'taste_salty' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * A flash discount is live only if it hasn't expired and portions remain —
     * computed fresh on every call, never a stale stored flag.
     */
    public function hasLiveDiscount(): bool
    {
        return $this->discount_percent !== null
            && $this->discount_ends_at !== null
            && $this->discount_ends_at->isFuture()
            && $this->discount_portions_remaining !== null
            && $this->discount_portions_remaining > 0;
    }

    public function effectivePrice(): float
    {
        if (! $this->hasLiveDiscount()) {
            return (float) $this->price;
        }

        return round((float) $this->price * (1 - $this->discount_percent / 100), 2);
    }

    public function hasTasteProfile(): bool
    {
        return $this->taste_spicy !== null && $this->taste_sweet !== null && $this->taste_salty !== null;
    }
}
