<?php

use App\Enums\Role as RoleEnum;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A staff member of the given restaurant.
 */
function staffOf(Restaurant $restaurant): User
{
    $user = User::factory()->ofRestaurant($restaurant)->create();
    $user->assignRole(RoleEnum::Staff->value);

    return $user;
}

function staffUrl(Restaurant $restaurant, string $path = ''): string
{
    return 'http://'.$restaurant->slug.'.restaurant-app.test/staff'.$path;
}

/*
|--------------------------------------------------------------------------
| Getting in
|--------------------------------------------------------------------------
*/

it('serves the staff login page', function (): void {
    $restaurant = Restaurant::factory()->create();

    $this->get(staffUrl($restaurant, '/login'))
        ->assertOk()
        ->assertSee('data-page');
});

it('sends a guest to the login page of the restaurant they are on', function (): void {
    $restaurant = Restaurant::factory()->create();

    $this->get(staffUrl($restaurant))
        ->assertRedirect(staffUrl($restaurant, '/login'));
});

it('emails a code to someone on this restaurant\'s roster', function (): void {
    $restaurant = Restaurant::factory()->create();
    $staff = staffOf($restaurant);
    $readCodes = captureIssuedCodes();

    $this->post(staffUrl($restaurant, '/sign-in-codes'), ['email' => $staff->email])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($readCodes())->toHaveCount(1);
});

it('refuses a code for someone who does not work here', function (): void {
    $restaurant = Restaurant::factory()->create();
    $elsewhere = staffOf(Restaurant::factory()->create());
    $readCodes = captureIssuedCodes();

    $this->post(staffUrl($restaurant, '/sign-in-codes'), ['email' => $elsewhere->email])
        ->assertSessionHasErrors('email');

    expect($readCodes())->toBeEmpty();
});

it('refuses a code for an address with no account', function (): void {
    $restaurant = Restaurant::factory()->create();

    $this->post(staffUrl($restaurant, '/sign-in-codes'), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
});

it('signs in with the emailed code', function (): void {
    $restaurant = Restaurant::factory()->create();
    $staff = staffOf($restaurant);
    $readCodes = captureIssuedCodes();

    $this->post(staffUrl($restaurant, '/sign-in-codes'), ['email' => $staff->email]);
    $code = $readCodes()[0];

    $this->post(staffUrl($restaurant, '/session'), ['email' => $staff->email, 'code' => $code])
        ->assertRedirect(staffUrl($restaurant));

    $this->assertAuthenticatedAs($staff);
});

it('refuses a wrong code', function (): void {
    $restaurant = Restaurant::factory()->create();
    $staff = staffOf($restaurant);
    $readCodes = captureIssuedCodes();

    $this->post(staffUrl($restaurant, '/sign-in-codes'), ['email' => $staff->email]);
    $readCodes();

    $this->post(staffUrl($restaurant, '/session'), ['email' => $staff->email, 'code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('will not let one restaurant\'s code open another\'s staff app', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $staff = staffOf($theirs);
    $readCodes = captureIssuedCodes();

    // A real code, issued properly for the restaurant they actually work at.
    $this->post(staffUrl($theirs, '/sign-in-codes'), ['email' => $staff->email]);
    $code = $readCodes()[0];

    // Presented at a restaurant they have nothing to do with.
    $this->post(staffUrl($mine, '/session'), ['email' => $staff->email, 'code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('signs out', function (): void {
    $restaurant = Restaurant::factory()->create();
    $staff = staffOf($restaurant);

    $this->actingAs($staff)
        ->delete(staffUrl($restaurant, '/session'))
        ->assertRedirect(staffUrl($restaurant, '/login'));

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Once inside
|--------------------------------------------------------------------------
*/

it('shows the floor what is on and what has sold out', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $available = MenuItem::factory()->inCategory($category)->create();
    $soldOut = MenuItem::factory()->inCategory($category)->unavailable()->create();

    // Unlike the guest menu, staff see sold-out dishes: they are asked for
    // them all evening and need to know to say no.
    $this->actingAs(staffOf($restaurant))
        ->get(staffUrl($restaurant))
        ->assertOk()
        ->assertSee($available->name)
        ->assertSee($soldOut->name);
});

it('keeps a staff member out of another restaurant\'s app', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $this->actingAs(staffOf($theirs))
        ->get(staffUrl($mine))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| It is installable, and the guest app is not
|--------------------------------------------------------------------------
*/

it('serves a manifest branded for the restaurant', function (): void {
    $restaurant = Restaurant::factory()->create(['name' => 'Spice Garden']);
    $restaurant->settings->update(['theme_primary_color' => '#0EA5E9']);

    $this->get(staffUrl($restaurant, '/manifest.webmanifest'))
        ->assertOk()
        ->assertJsonPath('short_name', 'Spice Garden')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('theme_color', '#0EA5E9')
        ->assertJsonPath('start_url', '/staff');
});

it('serves a service worker scoped to the staff app', function (): void {
    $restaurant = Restaurant::factory()->create();

    $response = $this->get(staffUrl($restaurant, '/service-worker.js'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/javascript')
        ->assertHeader('Service-Worker-Allowed', '/staff/login');

    // Network first, always: there is no offline requirement, and a stale
    // menu shown to a table would be worse than a spinner.
    expect($response->getContent())->toContain('fetch(event.request)');
});
