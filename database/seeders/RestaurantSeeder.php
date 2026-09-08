<?php

namespace Database\Seeders;

use App\Enums\CountryCallingCode;
use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\Role;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * One restaurant, its settings, and the one administrator who runs it.
 *
 * The admin address uses plus-addressing so every restaurant gets a distinct
 * account while the sign-in codes all land in the same real inbox.
 */
class RestaurantSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The restaurants to seed, keyed by the slug that becomes their subdomain.
     *
     * @var list<array{slug: string, name: string, address: string, pincode: string, email: string, phone: string, admin_name: string, admin_email: string}>
     */
    public const array RESTAURANTS = [
        [
            'slug' => 'spice',
            'name' => 'Spice Garden',
            'address' => '12 Mount Road, Chennai',
            'pincode' => '600002',
            'email' => 'hello@spicegarden.example.com',
            'phone' => '9876543210',
            'admin_name' => 'Spice Garden Admin',
            'admin_email' => 'gopalwebdev+spice@gmail.com',
        ],
    ];

    /**
     * The starter menu every seeded restaurant gets.
     *
     * @var list<array{name: string, items: list<array{name: string, price_minor_units: int, food_type: FoodType}>}>
     */
    public const array MENU = [
        [
            'name' => 'Starters',
            'items' => [
                ['name' => 'Paneer Tikka', 'price_minor_units' => 24950, 'food_type' => FoodType::Vegetarian],
                ['name' => 'Gobi Manchurian', 'price_minor_units' => 21000, 'food_type' => FoodType::Vegetarian],
                ['name' => 'Chicken 65', 'price_minor_units' => 29900, 'food_type' => FoodType::NonVegetarian],
            ],
        ],
        [
            'name' => 'Biryani',
            'items' => [
                ['name' => 'Hyderabadi Chicken Biryani', 'price_minor_units' => 38000, 'food_type' => FoodType::NonVegetarian],
                ['name' => 'Vegetable Dum Biryani', 'price_minor_units' => 30000, 'food_type' => FoodType::Vegetarian],
                ['name' => 'Egg Biryani', 'price_minor_units' => 27500, 'food_type' => FoodType::Egg],
            ],
        ],
        [
            'name' => 'Breads',
            'items' => [
                ['name' => 'Butter Naan', 'price_minor_units' => 8000, 'food_type' => FoodType::Vegetarian],
                ['name' => 'Tandoori Roti', 'price_minor_units' => 5000, 'food_type' => FoodType::Vegetarian],
            ],
        ],
        [
            'name' => 'Desserts',
            'items' => [
                ['name' => 'Gulab Jamun', 'price_minor_units' => 12000, 'food_type' => FoodType::Vegetarian],
                ['name' => 'Rasmalai', 'price_minor_units' => 14000, 'food_type' => FoodType::Vegetarian],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::RESTAURANTS as $definition) {
            $restaurant = Restaurant::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'address' => $definition['address'],
                    'pincode' => $definition['pincode'],
                    'email' => $definition['email'],
                    'phone_country_code' => CountryCallingCode::India,
                    'phone' => $definition['phone'],
                    'is_active' => true,
                ],
            );

            $restaurant->settings()->firstOrCreate([], [
                'contact_email' => "hello@{$definition['slug']}.example.com",
                'contact_phone' => '+91 98765 43210',
                'timezone' => 'Asia/Kolkata',
                'currency' => Currency::IndianRupee,
                'accepts_orders' => true,
                'opens_at' => '09:00:00',
                'closes_at' => '23:00:00',
            ]);

            $admin = User::query()->firstOrCreate(
                ['email' => $definition['admin_email']],
                ['name' => $definition['admin_name'], 'email_verified_at' => now()],
            );

            $admin->syncRoles([Role::Admin->value]);
            $restaurant->users()->syncWithoutDetaching([$admin->getKey()]);

            $this->seedMenu($restaurant);
        }
    }

    /**
     * A small menu, so a fresh install has something to look at.
     *
     * Prices are in the minor unit, as the column is: 24950 is ₹249.50.
     */
    private function seedMenu(Restaurant $restaurant): void
    {
        foreach (self::MENU as $position => $section) {
            $category = MenuCategory::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->getKey(), 'name' => $section['name']],
                ['position' => $position, 'is_active' => true],
            );

            foreach ($section['items'] as $itemPosition => $item) {
                MenuItem::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->getKey(), 'name' => $item['name']],
                    [
                        'menu_category_id' => $category->getKey(),
                        'price_minor_units' => $item['price_minor_units'],
                        'food_type' => $item['food_type'],
                        'is_available' => true,
                        'position' => $itemPosition,
                    ],
                );
            }
        }
    }
}
