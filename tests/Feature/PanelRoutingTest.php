<?php

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Panel resolution
|--------------------------------------------------------------------------
|
| Both panels live at /admin and are told apart only by host, so these tests
| pin the behaviour that the root domain reaches the platform panel and a
| restaurant subdomain reaches that restaurant's panel.
|
*/

it('serves the platform panel on the root domain', function (): void {
    $this->get('http://restaurant-app.test/admin/login')
        ->assertOk()
        ->assertSee('Restaurant Platform');
});

it('serves the restaurant panel on a restaurant subdomain', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/admin/login')
        ->assertOk()
        ->assertDontSee('Restaurant Platform');
});

it('keeps the two panels on separate hosts', function (): void {
    expect(route('filament.super-admin.auth.login'))
        ->toBe('http://restaurant-app.test/admin/login');
});

/*
|--------------------------------------------------------------------------
| Panel authorisation
|--------------------------------------------------------------------------
*/

it('lets a super admin into the platform panel', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SuperAdmin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/admin')
        ->assertOk();
});

it('keeps a restaurant admin out of the platform panel', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/admin')
        ->assertForbidden();
});

it('lets a restaurant admin into their own restaurant panel', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/admin')
        ->assertOk();
});

it('stops a restaurant admin reaching another restaurant by changing the subdomain', function (): void {
    $own = Restaurant::factory()->create(['slug' => 't1']);
    Restaurant::factory()->create(['slug' => 't2']);

    $user = User::factory()->create();
    $user->restaurants()->attach($own);
    $user->assignRole(Role::Admin->value);

    // Filament answers 404 rather than 403 here on purpose: a stranger must not
    // be able to learn that t2 exists by reading the status code.
    $this->actingAs($user)
        ->get('http://t2.restaurant-app.test/admin')
        ->assertNotFound();
});

it('lets a super admin support any restaurant panel', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $user = User::factory()->create();
    $user->assignRole(Role::SuperAdmin->value);

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/admin')
        ->assertOk();
});

it('redirects a guest on a restaurant panel to that restaurant login', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/admin')
        ->assertRedirect('http://t1.restaurant-app.test/admin/login');
});
