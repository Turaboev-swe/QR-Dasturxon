<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Chef;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\Staff;
use App\Models\TelegramUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one sample restaurant for local
     * development and manual testing (e.g. `telegram:fake-init-data`'s
     * default `start_param=r1_t1`). Safe to re-run: content is synced via
     * updateOrCreate, except `radius_meters` which is left untouched once
     * a restaurant exists (a developer may have widened it for a live
     * on-device test).
     */
    public function run(): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['id' => 1],
            [
                'name_translations' => [
                    'uz' => "Chorbog' Milliy Oshxonasi",
                    'en' => 'Chorbogh National Restaurant',
                    'ru' => 'Национальный ресторан Чорбог',
                ],
                'latitude' => 41.311081,
                'longitude' => 69.240562,
                'is_active' => true,
                'is_verified' => true,
                'badge_text' => 'TOP-10 milliy oshxona',
                'kitchen_chat_id' => config('services.telegram.kitchen_chat_id'),
                'waiter_chat_id' => config('services.telegram.waiter_chat_id'),
            ],
        );

        if ($restaurant->wasRecentlyCreated) {
            $restaurant->update(['radius_meters' => 150]);
        }

        $table = RestaurantTable::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => '1'],
            ['name' => 'Stol 1', 'is_active' => true],
        );

        // Tables 2-20 so every printed QR code (qr-generator.html /
        // generate_qrs.py generate one per table 1-20) resolves to a real,
        // active table instead of TableResolver's "Stol topilmadi".
        for ($i = 2; $i <= 20; $i++) {
            RestaurantTable::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => (string) $i],
                ['name' => "Stol {$i}", 'is_active' => true],
            );
        }

        Chef::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => "Ustoz Oybek Qodirov"],
            [
                'title' => 'Bosh oshpaz',
                'experience_years' => 18,
                'specialty' => "Milliy taomlar bo'yicha mutaxassis",
                'tier_badge' => 'Yuqori toifa',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $categories = [
            'asosiy' => Category::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name_translations->uz' => 'Asosiy'],
                ['name_translations' => ['uz' => 'Asosiy', 'en' => 'Main', 'ru' => 'Основные'], 'sort_order' => 1, 'is_active' => true],
            ),
            'shorva' => Category::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name_translations->uz' => "Sho'rva"],
                ['name_translations' => ['uz' => "Sho'rva", 'en' => 'Soup', 'ru' => 'Суп'], 'sort_order' => 2, 'is_active' => true],
            ),
            'salat' => Category::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name_translations->uz' => 'Salat'],
                ['name_translations' => ['uz' => 'Salat', 'en' => 'Salad', 'ru' => 'Салат'], 'sort_order' => 3, 'is_active' => true],
            ),
            'ichimlik' => Category::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name_translations->uz' => 'Ichimliklar'],
                ['name_translations' => ['uz' => 'Ichimliklar', 'en' => 'Drinks', 'ru' => 'Напитки'], 'sort_order' => 4, 'is_active' => true],
            ),
        ];

        $dishes = [
            [
                'category' => 'asosiy',
                'name' => ['uz' => 'Toshkent Palov', 'en' => 'Tashkent Plov', 'ru' => 'Ташкентский плов'],
                'price' => 32000,
                'allergens' => null,
                'sort_order' => 1,
                'taste' => ['spicy' => 20, 'sweet' => 15, 'salty' => 55],
            ],
            [
                'category' => 'shorva',
                'name' => ['uz' => "Lag'mon", 'en' => 'Lagman', 'ru' => 'Лагман'],
                'price' => 28000,
                'allergens' => ['gluten'],
                'sort_order' => 1,
                'taste' => ['spicy' => 40, 'sweet' => 10, 'salty' => 45],
            ],
            [
                'category' => 'asosiy',
                'name' => ['uz' => 'Norin', 'en' => 'Norin', 'ru' => 'Норин'],
                'price' => 35000,
                'allergens' => ['gluten'],
                'sort_order' => 2,
                'taste' => ['spicy' => 10, 'sweet' => 5, 'salty' => 60],
            ],
            [
                'category' => 'shorva',
                'name' => ['uz' => 'Mastava', 'en' => 'Mastava', 'ru' => 'Мастава'],
                'price' => 18000,
                'allergens' => null,
                'sort_order' => 2,
                'taste' => ['spicy' => 15, 'sweet' => 10, 'salty' => 50],
            ],
        ];

        foreach ($dishes as $dish) {
            Dish::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $categories[$dish['category']]->id,
                    'name_translations->uz' => $dish['name']['uz'],
                ],
                [
                    'name_translations' => $dish['name'],
                    'price' => $dish['price'],
                    'allergens' => $dish['allergens'],
                    'sort_order' => $dish['sort_order'],
                    'is_available' => true,
                    'taste_spicy' => $dish['taste']['spicy'],
                    'taste_sweet' => $dish['taste']['sweet'],
                    'taste_salty' => $dish['taste']['salty'],
                ],
            );
        }

        // Staff are identified purely by Telegram id (no login screen,
        // same principle as customers) — the restaurant pre-registers
        // whichever Telegram account belongs to each employee. The admin
        // row below is the confirmed real restaurant owner/admin
        // (@abdulqayum_dev, Telegram id 1746546661) — not a dev placeholder.
        Staff::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'telegram_id' => 1746546661],
            ['name' => 'Chorbog\' Egasi', 'role' => Staff::ROLE_ADMIN, 'phone' => null, 'is_active' => true],
        );

        Staff::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'telegram_id' => 900000100],
            ['name' => 'Chorbog\' Kassir (namuna)', 'role' => Staff::ROLE_CASHIER, 'phone' => null, 'is_active' => true],
        );

        // A handful of real reviews so the restaurant's rating/reviews_count
        // in the API response come from genuine aggregated data.
        $reviews = [
            ['rating' => 5, 'comment' => "Lag'mon juda mazali edi, oshpaz haqiqatan ustasi ekan sezildi."],
            ['rating' => 5, 'comment' => 'Xizmat va taomlar a\'lo darajada.'],
            ['rating' => 4, 'comment' => 'Palov yaxshi, lekin biroz kutdik.'],
            ['rating' => 5, 'comment' => null],
            ['rating' => 5, 'comment' => 'Norin ajoyib, albatta qaytib kelaman.'],
            ['rating' => 5, 'comment' => null],
        ];

        foreach ($reviews as $i => $reviewData) {
            $telegramUser = TelegramUser::updateOrCreate(
                ['telegram_id' => 900000000 + $i],
                ['first_name' => 'Mijoz '.($i + 1)],
            );

            $order = Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'telegram_user_id' => $telegramUser->id],
                ['restaurant_table_id' => $table->id, 'status' => Order::STATUS_PAID, 'total_price' => 40000],
            );

            Review::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'telegram_user_id' => $telegramUser->id,
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'],
                ],
            );
        }
    }
}
