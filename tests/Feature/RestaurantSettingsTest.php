<?php

use App\Enums\AdminPanel;
use App\Enums\Currency;
use App\Enums\Role;
use App\Filament\Admin\Pages\Settings;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Sign in as a user of the given restaurant and put the panel in that
 * restaurant's context, the way a request to its subdomain would.
 */
function enterRestaurantPanel(Restaurant $restaurant, Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $user->restaurants()->attach($restaurant);

    test()->actingAs($user);
    Filament::setCurrentPanel(AdminPanel::Admin->value);
    Filament::setTenant($restaurant);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Reading settings
|--------------------------------------------------------------------------
*/

it('shows the settings of the restaurant whose panel it is', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings()->update([
        'contact_email' => 'hello@spice.example.com',
        'currency' => Currency::PoundSterling,
    ]);
    enterRestaurantPanel($restaurant, Role::Admin);

    Livewire::test(Settings::class)
        ->assertFormSet([
            'contact_email' => 'hello@spice.example.com',
            'currency' => Currency::PoundSterling->value,
        ]);
});

it('never shows another restaurant settings', function (): void {
    $own = Restaurant::factory()->create();
    $own->settings()->update(['contact_email' => 'ours@example.com']);

    $other = Restaurant::factory()->create();
    $other->settings()->update(['contact_email' => 'theirs@example.com']);

    enterRestaurantPanel($own, Role::Admin);

    Livewire::test(Settings::class)
        ->assertFormSet(['contact_email' => 'ours@example.com']);
});

it('creates settings on first view if a restaurant somehow has none', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings()->delete();
    enterRestaurantPanel($restaurant, Role::Admin);

    Livewire::test(Settings::class)->assertOk();

    expect($restaurant->refresh()->settings)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Writing settings
|--------------------------------------------------------------------------
*/

it('saves changes against the restaurant in the panel', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, Role::Admin);

    Livewire::test(Settings::class)
        ->fillForm([
            'contact_email' => 'new@example.com',
            'contact_phone' => '+44 20 7946 0000',
            'timezone' => 'Europe/London',
            'currency' => Currency::PoundSterling->value,
            'accepts_orders' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = $restaurant->refresh()->settings;

    expect($settings->contact_email)->toBe('new@example.com')
        ->and($settings->timezone)->toBe('Europe/London')
        ->and($settings->currency)->toBe(Currency::PoundSterling)
        ->and($settings->accepts_orders)->toBeFalse();
});

it('leaves other restaurants settings alone when saving', function (): void {
    $own = Restaurant::factory()->create();
    $other = Restaurant::factory()->create();
    $other->settings()->update(['contact_email' => 'theirs@example.com']);

    enterRestaurantPanel($own, Role::Admin);

    Livewire::test(Settings::class)
        ->fillForm(['contact_email' => 'ours@example.com'])
        ->call('save');

    expect($other->refresh()->settings->contact_email)->toBe('theirs@example.com');
});

it('insists on a timezone and a currency', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, Role::Admin);

    Livewire::test(Settings::class)
        ->fillForm(['timezone' => null, 'currency' => null])
        ->call('save')
        ->assertHasFormErrors(['timezone' => 'required', 'currency' => 'required']);
});

it('rejects an address that is not an email', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, Role::Admin);

    Livewire::test(Settings::class)
        ->fillForm(['contact_email' => 'not-an-email'])
        ->call('save')
        ->assertHasFormErrors(['contact_email' => 'email']);
});

/*
|--------------------------------------------------------------------------
| Who may see the page
|--------------------------------------------------------------------------
*/

it('is open to the restaurant admin', function (): void {
    enterRestaurantPanel(Restaurant::factory()->create(), Role::Admin);

    expect(Settings::canAccess())->toBeTrue();
});

it('is closed to floor staff', function (): void {
    enterRestaurantPanel(Restaurant::factory()->create(), Role::Staff);

    expect(Settings::canAccess())->toBeFalse();
});

it('is open to a super admin supporting a restaurant', function (): void {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user);
    Filament::setCurrentPanel(AdminPanel::Admin->value);
    Filament::setTenant($restaurant);

    expect(Settings::canAccess())->toBeTrue();
});
