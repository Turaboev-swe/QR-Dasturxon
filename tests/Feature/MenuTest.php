<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\TelegramUser;
use App\Services\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name_translations' => ['uz' => 'Namuna Restorani'],
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'code' => '1', 'is_active' => true]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_translations' => ['uz' => 'Taomlar'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->dish = Dish::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_translations' => ['uz' => 'Osh'],
            'price' => 20000,
            'sort_order' => 1,
            'is_available' => true,
        ]);
    }

    private function customerHeader(): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'start_param' => 'r'.$this->restaurant->id.'_t1',
            'user' => json_encode(['id' => 111222333, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    private function customerHeaderWithoutStartParam(): array
    {
        $initData = app(TelegramAuth::class)->sign([
            'auth_date' => (string) time(),
            'query_id' => 'AA1',
            'user' => json_encode(['id' => 111222333, 'first_name' => 'Test', 'language_code' => 'uz']),
        ]);

        return ['X-Telegram-Init-Data' => $initData];
    }

    public function test_menu_is_served_with_a_valid_start_param_and_is_not_marked_demo(): void
    {
        $response = $this->getJson('/api/menu', $this->customerHeader());

        $response->assertStatus(200)->assertJson(['demo' => false]);
    }

    public function test_menu_falls_back_to_a_read_only_demo_view_without_a_start_param(): void
    {
        $response = $this->getJson('/api/menu', $this->customerHeaderWithoutStartParam());

        $response->assertStatus(200)->assertJson(['demo' => true]);
        $this->assertSame('Namuna Restorani', $response->json('restaurant.name'));
        $dish = collect($response->json('categories.0.dishes'))->firstWhere('id', $this->dish->id);
        $this->assertNotNull($dish);
    }

    public function test_menu_without_a_start_param_returns_422_when_no_restaurant_is_active(): void
    {
        $this->restaurant->update(['is_active' => false]);

        $response = $this->getJson('/api/menu', $this->customerHeaderWithoutStartParam());

        $response->assertStatus(422);
    }

    public function test_live_discount_is_reflected_in_the_menu(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->addMinutes(30),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 4,
        ]);

        $response = $this->getJson('/api/menu', $this->customerHeader());

        $response->assertStatus(200);
        $dish = collect($response->json('categories.0.dishes'))->firstWhere('id', $this->dish->id);
        $this->assertNotNull($dish['discount']);
        $this->assertSame(50, $dish['discount']['percent']);
        $this->assertSame(10000.0, (float) $dish['discount']['price']);
        $this->assertSame(4, $dish['discount']['portions_remaining']);
    }

    public function test_expired_discount_does_not_appear_even_though_db_row_still_has_it(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->subMinute(),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 5,
        ]);

        $response = $this->getJson('/api/menu', $this->customerHeader());

        $dish = collect($response->json('categories.0.dishes'))->firstWhere('id', $this->dish->id);
        $this->assertNull($dish['discount']);
    }

    public function test_exhausted_discount_does_not_appear(): void
    {
        $this->dish->update([
            'discount_percent' => 50,
            'discount_ends_at' => now()->addMinutes(30),
            'discount_portions_total' => 5,
            'discount_portions_remaining' => 0,
        ]);

        $response = $this->getJson('/api/menu', $this->customerHeader());

        $dish = collect($response->json('categories.0.dishes'))->firstWhere('id', $this->dish->id);
        $this->assertNull($dish['discount']);
    }

    public function test_recent_reviews_are_included_without_pii(): void
    {
        $telegramUser = TelegramUser::create(['telegram_id' => 555, 'first_name' => 'Ali']);
        $table = RestaurantTable::where('restaurant_id', $this->restaurant->id)->first();
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $table->id,
            'telegram_user_id' => $telegramUser->id,
            'status' => Order::STATUS_PAID,
            'total_price' => 20000,
        ]);
        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'telegram_user_id' => $telegramUser->id,
            'rating' => 5,
            'comment' => 'Ajoyib taom!',
        ]);

        $response = $this->getJson('/api/menu', $this->customerHeader());

        $response->assertStatus(200);
        $review = $response->json('restaurant.recent_reviews.0');
        $this->assertSame('Ajoyib taom!', $review['comment']);
        $this->assertSame('Ali', $review['name']);
        $this->assertSame(5, $review['rating']);
        $this->assertArrayNotHasKey('telegram_id', $review);
        $this->assertArrayNotHasKey('telegram_user_id', $review);
    }
}
