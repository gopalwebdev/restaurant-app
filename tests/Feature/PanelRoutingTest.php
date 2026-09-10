<?php

use App\Enums\AdminPanel;
use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Panel resolution
|--------------------------------------------------------------------------
|
| Both panels live at /admin and are told apart only by host, so these tests
| pin the behaviour that the root domain reaches the product team panel and a
| restaurant subdomain reaches that restaurant's panel.
|
*/

it('serves the product team panel on the root domain', function (): void {
    $this->get('http://restaurant-app.test/admin/login')
        ->assertOk()
        ->assertSee('Restaurant Platform');
});

it('serves each panel from the path its enum declares', function (AdminPanel $panel): void {
    expect(Filament::getPanel($panel->value)->getPath())->toBe($panel->path());
})->with(AdminPanel::cases());

it('sends a guest on the product team panel to its own sign-in', function (): void {
    $this->get('http://restaurant-app.test/admin/restaurants')
        ->assertRedirect('http://restaurant-app.test/admin/login');
});

it('does not serve the product team panel from a restaurant subdomain', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    // Same path, other host: /admin on a subdomain is that restaurant's panel,
    // and the product team's pages are not part of it.
    $this->get('http://t1.restaurant-app.test/admin/login')
        ->assertOk()
        ->assertDontSee('Restaurant Platform');

    $this->get('http://t1.restaurant-app.test/admin/restaurants')->assertNotFound();
});

it('serves the restaurant panel on a restaurant subdomain', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/admin/login')
        ->assertOk()
        ->assertDontSee('Restaurant Platform');
});

it('serves both panels at /admin, told apart by host', function (): void {
    $restaurant = Restaurant::factory()->make(['slug' => 't1']);

    // The restaurant panel's sign-in route carries no domain — nobody has a
    // tenant before signing in — so its link is built with the subdomain on,
    // and the root domain's /admin/login is the product team's.
    expect(route('filament.super-admin.auth.login'))
        ->toBe('http://restaurant-app.test/admin/login')
        ->and($restaurant->adminSignInUrl())
        ->toBe('http://t1.restaurant-app.test/admin/login');
});

/*
|--------------------------------------------------------------------------
| Panel authorisation
|--------------------------------------------------------------------------
*/

it('lets a super admin into the product team panel', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://restaurant-app.test/admin')
        ->assertOk();
});

it('gates the product team panel on the is_super_admin column alone', function (): void {
    $user = User::factory()->create();

    // Every restaurant role there is, and still no way in.
    foreach (Role::cases() as $role) {
        $user->assignRole($role->value);
    }

    $this->actingAs($user)
        ->get('http://restaurant-app.test/admin')
        ->assertForbidden();

    $user->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($user->fresh())
        ->get('http://restaurant-app.test/admin')
        ->assertOk();
});

it('keeps a restaurant admin out of the product team panel', function (): void {
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

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/admin')
        ->assertOk();
});

it('redirects a guest on a restaurant panel to that restaurant login', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/admin')
        ->assertRedirect('http://t1.restaurant-app.test/admin/login');
});

/*
|--------------------------------------------------------------------------
| Branding
|--------------------------------------------------------------------------
|
| A restaurant's own name replaces the generic panel name once someone is
| signed in and a tenant is known — see AdminPanelProvider::brandName(). The
| tenant menu is off (.ai/rules/filament.md), so there is nowhere for another
| restaurant's name to leak into this page at all.
*/

it('shows the generic panel name before anyone signs in', function (): void {
    Restaurant::factory()->create(['slug' => 't1', 'name' => 'Spice Garden']);

    $this->get('http://t1.restaurant-app.test/admin/login')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertDontSee('Spice Garden');
});

it("shows the restaurant's own name once someone is signed in", function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1', 'name' => 'Spice Garden']);
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/admin')
        ->assertOk()
        ->assertSee('Spice Garden')
        ->assertDontSee(config('app.name'));
});

it('gives a super admin no tenant menu to switch restaurants from', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1', 'name' => 'Spice Garden']);
    $other = Restaurant::factory()->create(['name' => 'Other Place']);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/admin')
        ->assertOk()
        ->assertSee('Spice Garden')
        ->assertDontSee('Other Place');
});
