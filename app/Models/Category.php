<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'name_translations', 'sort_order', 'is_active'])]
class Category extends Model
{
    use HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }
}
