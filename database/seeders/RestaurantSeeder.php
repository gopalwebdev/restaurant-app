<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\Role;
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
     * @var list<array{slug: string, name: string, admin_name: string, admin_email: string}>
     */
    public const array RESTAURANTS = [
        [
            'slug' => 'spice',
            'name' => 'Spice Garden',
            'admin_name' => 'Spice Garden Admin',
            'admin_email' => 'gopalwebdev+spice@gmail.com',
        ],
    ];

    public function run(): void
    {
        foreach (self::RESTAURANTS as $definition) {
            $restaurant = Restaurant::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                ['name' => $definition['name'], 'is_active' => true],
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
        }
    }
}
