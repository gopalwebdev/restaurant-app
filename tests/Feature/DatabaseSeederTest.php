<?php

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RestaurantSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seeds exactly one platform owner', function (): void {
    $superAdmins = User::query()->superAdmins()->get();

    expect($superAdmins)->toHaveCount(1)
        ->and($superAdmins->first()->email)->toBe(SuperAdminSeeder::EMAIL);
});

it('gives the platform owner no password to store', function (): void {
    // The users table carries no password column at all, so there is nothing
    // to leave null and nothing to leak.
    expect(Schema::hasColumn('users', 'password'))->toBeFalse();
});

it('seeds one restaurant for each definition', function (): void {
    expect(Restaurant::query()->count())->toBe(count(RestaurantSeeder::RESTAURANTS));

    foreach (RestaurantSeeder::RESTAURANTS as $definition) {
        expect(Restaurant::query()->where('slug', $definition['slug'])->exists())->toBeTrue();
    }
});

it('gives every seeded restaurant its settings', function (): void {
    Restaurant::query()->get()->each(function (Restaurant $restaurant): void {
        expect($restaurant->settings)->not->toBeNull();
    });
});

it('gives every seeded restaurant exactly one admin', function (): void {
    Restaurant::query()->get()->each(function (Restaurant $restaurant): void {
        $admins = $restaurant->users()->role(Role::Admin->value)->get();

        expect($admins)->toHaveCount(1);
    });
});

it('lets each restaurant admin into their own panel and no further', function (): void {
    $restaurant = Restaurant::query()->firstOrFail();
    $admin = $restaurant->users()->role(Role::Admin->value)->firstOrFail();

    $this->actingAs($admin)
        ->get("http://{$restaurant->slug}.restaurant-app.test/admin")
        ->assertOk();

    $this->actingAs($admin)
        ->get('http://restaurant-app.test/super-admin')
        ->assertForbidden();
});

it('lets the platform owner into the platform panel', function (): void {
    $superAdmin = User::query()->where('email', SuperAdminSeeder::EMAIL)->firstOrFail();

    $this->actingAs($superAdmin)
        ->get('http://restaurant-app.test/super-admin')
        ->assertOk();
});

it('lists every account and where it signs in', function (): void {
    $restaurant = Restaurant::query()->firstOrFail();
    $admin = $restaurant->users()->role(Role::Admin->value)->firstOrFail();

    expect(Artisan::call('accounts:list'))->toBe(0);

    $output = Artisan::output();

    expect($output)
        ->toContain(SuperAdminSeeder::EMAIL)
        ->toContain('restaurant-app.test/super-admin')
        ->toContain($admin->email)
        ->toContain($restaurant->slug.'.restaurant-app.test/admin');
});

it('can be seeded again without duplicating anything', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1 + count(RestaurantSeeder::RESTAURANTS))
        ->and(Restaurant::query()->count())->toBe(count(RestaurantSeeder::RESTAURANTS));

    Restaurant::query()->get()->each(function (Restaurant $restaurant): void {
        expect($restaurant->users()->count())->toBe(1)
            ->and($restaurant->settings()->count())->toBe(1);
    });
});
