<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_translations', 'latitude', 'longitude', 'radius_meters', 'is_active', 'is_verified', 'badge_text', 'kitchen_chat_id', 'waiter_chat_id'])]
class Restaurant extends Model
{
    use HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'kitchen_chat_id' => 'integer',
            'waiter_chat_id' => 'integer',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function chefs(): HasMany
    {
        return $this->hasMany(Chef::class);
    }
}
