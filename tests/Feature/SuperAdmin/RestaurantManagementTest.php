<?php

use App\Enums\CountryCallingCode;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\SuperAdmin\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\SuperAdmin\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\SuperAdmin\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\SuperAdmin\Resources\Restaurants\RestaurantResource;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Who may manage restaurants
|--------------------------------------------------------------------------
|
| restaurant.manage is granted to no role, so the roster of restaurants is
| platform staff's alone. These check the policy directly, so a failure names
| the rule rather than a status code.
|
*/

it('lets platform staff manage restaurants', function (): void {
    $user = User::factory()->superAdmin()->create();
    $restaurant = Restaurant::factory()->create();

    expect($user->can('viewAny', Restaurant::class))->toBeTrue()
        ->and($user->can('create', Restaurant::class))->toBeTrue()
        ->and($user->can('update', $restaurant))->toBeTrue()
        ->and($user->can('delete', $restaurant))->toBeTrue();
});

it('refuses restaurant management to every restaurant role', function (Role $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $restaurant = Restaurant::factory()->create();

    expect($user->can('viewAny', Restaurant::class))->toBeFalse()
        ->and($user->can('create', Restaurant::class))->toBeFalse()
        ->and($user->can('update', $restaurant))->toBeFalse()
        ->and($user->can('delete', $restaurant))->toBeFalse();
})->with(Role::cases());

it('keeps a restaurant admin out of the restaurants page', function (): void {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();
    $user->restaurants()->attach($restaurant);
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/restaurants')
        ->assertForbidden();
});

it('serves the restaurants page to platform staff', function (): void {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://restaurant-app.test/super-admin/restaurants')
        ->assertOk();
});

it('hides the restaurants module from anyone without the permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    $this->actingAs($user);

    expect(RestaurantResource::canViewAny())->toBeFalse()
        ->and($user->can(Permission::RestaurantManage->value))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Creating
|--------------------------------------------------------------------------
*/

it('creates a restaurant with its address and contact details', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden',
            'slug' => 'spice',
            'address' => '12 Mount Road, Chennai',
            'pincode' => '600002',
            'email' => 'owner@spice.example.com',
            'phone_country_code' => CountryCallingCode::India->value,
            'phone' => '9876543210',
            'secondary_phone_country_code' => CountryCallingCode::India->value,
            'secondary_phone' => '9876543211',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $restaurant = Restaurant::query()->where('slug', 'spice')->sole();

    expect($restaurant->name)->toBe('Spice Garden')
        ->and($restaurant->address)->toBe('12 Mount Road, Chennai')
        ->and($restaurant->pincode)->toBe('600002')
        ->and($restaurant->email)->toBe('owner@spice.example.com')
        ->and($restaurant->phone_country_code)->toBe(CountryCallingCode::India)
        ->and($restaurant->phone)->toBe('9876543210')
        ->and($restaurant->secondary_phone_country_code)->toBe(CountryCallingCode::India)
        ->and($restaurant->secondary_phone)->toBe('9876543211')
        ->and($restaurant->is_active)->toBeTrue();
});

it('creates a restaurant without the optional contact details', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Corner Cafe',
            'slug' => 'corner',
            'address' => '3 Beach Road, Chennai',
            'pincode' => '600001',
            'phone' => '9000000000',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $restaurant = Restaurant::query()->where('slug', 'corner')->sole();

    expect($restaurant->email)->toBeNull()
        ->and($restaurant->secondary_phone)->toBeNull()
        // A country code with no number behind it is not kept.
        ->and($restaurant->secondary_phone_country_code)->toBeNull()
        ->and($restaurant->phone_country_code)->toBe(CountryCallingCode::India);
});

it('suggests a subdomain from the name', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm(['name' => 'Spice Garden'])
        ->assertFormSet(['slug' => 'spice-garden']);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('requires everything a restaurant cannot trade without', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => null,
            'slug' => null,
            'address' => null,
            'pincode' => null,
            'phone' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'slug' => 'required',
            'address' => 'required',
            'pincode' => 'required',
            'phone' => 'required',
        ]);

    expect(Restaurant::query()->count())->toBe(0);
});

it('refuses a subdomain that is not a valid DNS label', function (string $slug): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden',
            'slug' => $slug,
            'address' => '12 Mount Road',
            'pincode' => '600002',
            'phone' => '9876543210',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Restaurant::query()->count())->toBe(0);
})->with([
    'uppercase' => 'Spice',
    'underscored' => 'spice_garden',
    'leading hyphen' => '-spice',
    'trailing hyphen' => 'spice-',
    'dotted' => 'spice.garden',
]);

it('refuses a subdomain another restaurant already serves', function (): void {
    Restaurant::factory()->create(['slug' => 'spice']);
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden Two',
            'slug' => 'spice',
            'address' => '12 Mount Road',
            'pincode' => '600002',
            'phone' => '9876543210',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('refuses an address that is not an email address', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden',
            'slug' => 'spice',
            'address' => '12 Mount Road',
            'pincode' => '600002',
            'phone' => '9876543210',
            'email' => 'not-an-address',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

it('refuses a mobile number that is not ten digits', function (string $phone): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden',
            'slug' => 'spice',
            'address' => '12 Mount Road',
            'pincode' => '600002',
            'phone' => $phone,
        ])
        ->call('create')
        ->assertHasFormErrors(['phone']);

    expect(Restaurant::query()->count())->toBe(0);
})->with([
    'too short' => '987654321',
    'too long' => '98765432101',
    'with the country code' => '919876543210',
    'punctuated' => '98765 43210',
    'not digits' => 'nine one two',
]);

it('requires a country code for a secondary number', function (): void {
    enterPlatformPanel();

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => 'Spice Garden',
            'slug' => 'spice',
            'address' => '12 Mount Road',
            'pincode' => '600002',
            'phone' => '9876543210',
            'secondary_phone_country_code' => null,
            'secondary_phone' => '9876543211',
        ])
        ->call('create')
        ->assertHasFormErrors(['secondary_phone_country_code']);
});

it('writes a number back out with its country code', function (): void {
    $restaurant = Restaurant::factory()->create([
        'phone_country_code' => CountryCallingCode::India,
        'phone' => '9876543210',
        'secondary_phone_country_code' => null,
        'secondary_phone' => null,
    ]);

    expect($restaurant->dialablePhone())->toBe('+91 9876543210')
        ->and($restaurant->dialableSecondaryPhone())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Reading, updating and deleting
|--------------------------------------------------------------------------
*/

it('lists every restaurant on the platform', function (): void {
    $restaurants = Restaurant::factory()->count(3)->create();
    enterPlatformPanel();

    Livewire::test(ListRestaurants::class)
        ->assertCanSeeTableRecords($restaurants);
});

it('updates a restaurant', function (): void {
    $restaurant = Restaurant::factory()->create(['phone' => '9000000000']);
    enterPlatformPanel();

    Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
        ->fillForm([
            'name' => 'Renamed',
            'phone' => '9111111111',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($restaurant->refresh()->name)->toBe('Renamed')
        ->and($restaurant->phone)->toBe('9111111111')
        ->and($restaurant->is_active)->toBeFalse();
});

it('deletes a restaurant', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterPlatformPanel();

    Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
        ->callAction('delete');

    expect(Restaurant::query()->whereKey($restaurant->getKey())->exists())->toBeFalse();
});
