<?php

use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Admin\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Who may work on the menu
|--------------------------------------------------------------------------
|
| menu.view opens the pages and staff hold it; menu.manage is what changes
| anything, and only a restaurant admin has it.
|
*/

it('lets a restaurant admin manage the menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $admin = enterRestaurantPanel($restaurant, RoleEnum::Admin);
    $item = MenuItem::factory()->create();

    expect($admin->can('viewAny', MenuItem::class))->toBeTrue()
        ->and($admin->can('create', MenuItem::class))->toBeTrue()
        ->and($admin->can('update', $item))->toBeTrue()
        ->and($admin->can('delete', $item))->toBeTrue();
});

it('lets staff read the menu but not change it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $staff = enterRestaurantPanel($restaurant, RoleEnum::Staff);
    $item = MenuItem::factory()->create();

    expect($staff->can(PermissionEnum::MenuView->value))->toBeTrue()
        ->and($staff->can('viewAny', MenuItem::class))->toBeTrue()
        ->and($staff->can('create', MenuItem::class))->toBeFalse()
        ->and($staff->can('update', $item))->toBeFalse()
        ->and($staff->can('delete', $item))->toBeFalse();
});

it('keeps someone with no role off the menu pages', function (): void {
    $restaurant = Restaurant::factory()->create();
    $nobody = User::factory()->ofRestaurant($restaurant)->create();

    expect($nobody->can('viewAny', MenuItem::class))->toBeFalse()
        ->and($nobody->can('viewAny', MenuCategory::class))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Sections
|--------------------------------------------------------------------------
*/

it('creates a section against the restaurant whose panel it is', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', ['name' => 'Starters', 'position' => 1, 'is_active' => true]);

    $category = MenuCategory::query()->where('name', 'Starters')->sole();

    expect($category->restaurant_id)->toBe($restaurant->getKey())
        ->and($category->is_active)->toBeTrue();
});

it('refuses a section name the restaurant already uses', function (): void {
    $restaurant = Restaurant::factory()->create();
    MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey(), 'name' => 'Starters']);
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', ['name' => 'Starters', 'position' => 0, 'is_active' => true])
        ->assertHasActionErrors(['name' => 'unique']);
});

it('lets two restaurants both have a section of the same name', function (): void {
    $other = Restaurant::factory()->create();
    MenuCategory::factory()->create(['restaurant_id' => $other->getKey(), 'name' => 'Starters']);

    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', ['name' => 'Starters', 'position' => 0, 'is_active' => true])
        ->assertHasNoActionErrors();

    // The panel is booted, so MenuCategory carries a tenancy scope. Counting
    // across restaurants has to step outside it deliberately.
    expect(MenuCategory::query()->withoutGlobalScopes()->where('name', 'Starters')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Dishes, and the money they are priced in
|--------------------------------------------------------------------------
*/

it('stores a typed price as an exact integer count of minor units', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update(['currency' => Currency::IndianRupee]);
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => 'Paneer Tikka',
            'menu_category_id' => $category->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '249.50',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasNoActionErrors();

    $item = MenuItem::query()->where('name', 'Paneer Tikka')->sole();

    // ₹249.50 is 24950 paise, exactly. No float ever reaches the column.
    expect($item->price_minor_units)->toBe(24950)
        ->and($item->price_minor_units)->toBeInt()
        ->and($item->formattedPrice())->toBe('₹249.50')
        ->and($item->restaurant_id)->toBe($restaurant->getKey());
});

it('round-trips a price through the edit form without drift', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory($category)->create(['price_minor_units' => 24950]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->callAction(TestAction::make('edit')->table($item), [
            'name' => $item->name,
            'menu_category_id' => $category->getKey(),
            'food_type' => $item->food_type->value,
            'price' => '249.50',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasNoActionErrors();

    expect($item->refresh()->price_minor_units)->toBe(24950);
});

it('formats a price in the restaurant\'s own currency', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update(['currency' => Currency::UnitedStatesDollar]);
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory($category)->create(['price_minor_units' => 1250]);

    expect($item->formattedPrice())->toBe('$12.50');
});

/*
|--------------------------------------------------------------------------
| One restaurant never reaches another's menu
|--------------------------------------------------------------------------
*/

it('shows only this restaurant\'s dishes', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $myCategory = MenuCategory::factory()->create(['restaurant_id' => $mine->getKey()]);
    $theirCategory = MenuCategory::factory()->create(['restaurant_id' => $theirs->getKey()]);

    $myItem = MenuItem::factory()->inCategory($myCategory)->create();
    $theirItem = MenuItem::factory()->inCategory($theirCategory)->create();

    enterRestaurantPanel($mine, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords([$myItem])
        ->assertCanNotSeeTableRecords([$theirItem]);
});

it('refuses to file a dish under another restaurant\'s section', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    MenuCategory::factory()->create(['restaurant_id' => $mine->getKey()]);
    $theirCategory = MenuCategory::factory()->create(['restaurant_id' => $theirs->getKey()]);

    enterRestaurantPanel($mine, RoleEnum::Admin);

    // Only this restaurant's sections are offered, and Filament validates the
    // submitted value against that list — so a tampered id is rejected here,
    // before the composite foreign key would have refused the row anyway.
    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => 'Smuggled',
            'menu_category_id' => $theirCategory->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '100',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasActionErrors(['menu_category_id']);

    expect(MenuItem::query()->withoutGlobalScopes()->where('name', 'Smuggled')->exists())->toBeFalse();
});

it('refuses at the database to file a dish under another restaurant\'s section', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $theirCategory = MenuCategory::factory()->create(['restaurant_id' => $theirs->getKey()]);

    // The composite foreign key is the guarantee behind the tenant scope: even
    // a tampered request cannot store a dish pointing across restaurants.
    expect(fn () => DB::table('menu_items')->insert([
        'restaurant_id' => $mine->getKey(),
        'menu_category_id' => $theirCategory->getKey(),
        'name' => 'Smuggled',
        'price_minor_units' => 1000,
        'food_type' => FoodType::Vegetarian->value,
        'is_available' => true,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('takes a section\'s dishes with it when it is deleted', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory($category)->create();

    $category->delete();

    expect(MenuItem::query()->whereKey($item->getKey())->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| What a guest may actually order
|--------------------------------------------------------------------------
*/

it('counts an item orderable only when it and its section are showing', function (): void {
    $restaurant = Restaurant::factory()->create();

    $showing = MenuCategory::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $hidden = MenuCategory::factory()->hidden()->create(['restaurant_id' => $restaurant->getKey()]);

    $orderable = MenuItem::factory()->inCategory($showing)->create();
    $soldOut = MenuItem::factory()->inCategory($showing)->unavailable()->create();
    $inHiddenSection = MenuItem::factory()->inCategory($hidden)->create();

    $names = MenuItem::query()->orderable()->pluck('name')->all();

    expect($names)->toContain($orderable->name)
        ->and($names)->not->toContain($soldOut->name)
        ->and($names)->not->toContain($inHiddenSection->name);
});
