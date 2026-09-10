<?php

use App\Enums\FilamentPanel;
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
| Both panels live under /dashboard and are told apart only by host, so these tests
| pin the behaviour that the root domain reaches the product team panel and a
| restaurant subdomain reaches that restaurant's panel.
|
*/

it('serves the product team panel on the root domain', function (): void {
    $this->get('http://restaurant-app.test/dashboard/login')
        ->assertOk()
        ->assertSee('Restaurant Platform');
});

it('serves each panel from the path its enum declares', function (FilamentPanel $panel): void {
    expect(Filament::getPanel($panel->value)->getPath())->toBe($panel->path());
})->with(FilamentPanel::cases());

it('sends a guest on the product team panel to its own sign-in', function (): void {
    $this->get('http://restaurant-app.test/dashboard/restaurants')
        ->assertRedirect('http://restaurant-app.test/dashboard/login');
});

it('does not serve the product team panel from a restaurant subdomain', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    // Same path, other host: /dashboard on a subdomain is that restaurant's panel,
    // and the product team's pages are not part of it.
    $this->get('http://t1.restaurant-app.test/dashboard/login')
        ->assertOk()
        ->assertDontSee('Restaurant Platform');

    $this->get('http://t1.restaurant-app.test/dashboard/restaurants')->assertNotFound();
});

it('serves the restaurant panel on a restaurant subdomain', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/dashboard/login')
        ->assertOk()
        ->assertDontSee('Restaurant Platform');
});

it('serves both panels under /dashboard, told apart by host', function (): void {
    $restaurant = Restaurant::factory()->make(['slug' => 't1']);

    // The restaurant panel's own sign-in route carries no domain — nobody has
    // a tenant before signing in — so the root domain's /dashboard/login is the
    // product team's, and a restaurant's link is its subdomain's /login.
    expect(route('filament.platform.auth.login'))
        ->toBe('http://restaurant-app.test/dashboard/login')
        ->and($restaurant->signInUrl())
        ->toBe('http://t1.restaurant-app.test/login');
});

it('sends someone signed out from /login to the sign-in page of the panel on that host', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://restaurant-app.test/login')
        ->assertRedirect('http://restaurant-app.test/dashboard/login');

    $this->get('http://t1.restaurant-app.test/login')
        ->assertRedirect('http://t1.restaurant-app.test/dashboard/login');
});

it('sends someone already signed in from /login straight to /dashboard', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    $productTeam = User::factory()->superAdmin()->create();

    // Every account is made before the first request: a request into the
    // restaurant panel boots its tenancy, which attaches any user created after
    // it to that restaurant (.ai/rules/models.md).
    $members = array_map(function (Role $role) use ($restaurant): User {
        $member = User::factory()->create();
        $member->restaurants()->attach($restaurant);
        $member->assignRole($role->value);

        return $member;
    }, [Role::Admin, Role::Staff]);

    $this->actingAs($productTeam)
        ->get('http://restaurant-app.test/login')
        ->assertRedirect('http://restaurant-app.test/dashboard');

    // The restaurant panel is one door for everyone who works there: staff
    // arrive exactly where admins do.
    foreach ($members as $member) {
        $this->actingAs($member)
            ->get('http://t1.restaurant-app.test/login')
            ->assertRedirect('http://t1.restaurant-app.test/dashboard');

        $this->actingAs($member)
            ->get('http://t1.restaurant-app.test/dashboard')
            ->assertOk();
    }
});

/*
|--------------------------------------------------------------------------
| Panel authorisation
|--------------------------------------------------------------------------
*/

it('lets a super admin into the product team panel', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://restaurant-app.test/dashboard')
        ->assertOk();
});

it('gates the product team panel on the is_super_admin column alone', function (): void {
    $user = User::factory()->create();

    // Every restaurant role there is, and still no way in.
    foreach (Role::cases() as $role) {
        $user->assignRole($role->value);
    }

    $this->actingAs($user)
        ->get('http://restaurant-app.test/dashboard')
        ->assertForbidden();

    $user->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($user->fresh())
        ->get('http://restaurant-app.test/dashboard')
        ->assertOk();
});

it('keeps a restaurant admin out of the product team panel', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/dashboard')
        ->assertForbidden();
});

it('lets a restaurant admin into their own restaurant panel', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1']);
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/dashboard')
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
        ->get('http://t2.restaurant-app.test/dashboard')
        ->assertNotFound();
});

it('lets a super admin support any restaurant panel', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/dashboard')
        ->assertOk();
});

it('redirects a guest on a restaurant panel to that restaurant login', function (): void {
    Restaurant::factory()->create(['slug' => 't1']);

    $this->get('http://t1.restaurant-app.test/dashboard')
        ->assertRedirect('http://t1.restaurant-app.test/dashboard/login');
});

/*
|--------------------------------------------------------------------------
| Branding
|--------------------------------------------------------------------------
|
| A restaurant's own name replaces the generic panel name once someone is
| signed in and a tenant is known — see RestaurantPanelProvider::brandName(). The
| tenant menu is off (.ai/rules/filament.md), so there is nowhere for another
| restaurant's name to leak into this page at all.
*/

it('shows the generic panel name before anyone signs in', function (): void {
    Restaurant::factory()->create(['slug' => 't1', 'name' => 'Spice Garden']);

    $this->get('http://t1.restaurant-app.test/dashboard/login')
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
        ->get('http://t1.restaurant-app.test/dashboard')
        ->assertOk()
        ->assertSee('Spice Garden')
        ->assertDontSee(config('app.name'));
});

it('gives a super admin no tenant menu to switch restaurants from', function (): void {
    $restaurant = Restaurant::factory()->create(['slug' => 't1', 'name' => 'Spice Garden']);
    $other = Restaurant::factory()->create(['name' => 'Other Place']);

    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://t1.restaurant-app.test/dashboard')
        ->assertOk()
        ->assertSee('Spice Garden')
        ->assertDontSee('Other Place');
});
