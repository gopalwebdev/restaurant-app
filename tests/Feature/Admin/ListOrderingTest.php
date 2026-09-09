<?php

use App\Enums\Locale;
use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Resources\HomeRows\Pages\ListHomeRows;
use App\Filament\Admin\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Models\HomeRow;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Arranging a list with two buttons
|--------------------------------------------------------------------------
|
| Every hand-arranged list is ordered by moving one record at a time, rather
| than by typing numbers into a position field. See App\Filament\Tables\OrderActions.
|
*/

/** Read a menu's sections back in the order a guest would. */
function sectionOrder(Menu $menu): array
{
    return MenuCategory::query()
        ->withoutGlobalScopes()
        ->where('menu_id', $menu->getKey())
        ->orderBy('position')
        ->orderBy('id')
        ->get()
        ->map(fn (MenuCategory $section): string => $section->getTranslation('name', Locale::English->value))
        ->all();
}

it('moves a section up past the one above it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $first = MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters'], 'position' => 0]);
    $second = MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Mains'], 'position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction(TestAction::make('moveUp')->table($second));

    expect(sectionOrder($menu))->toBe(['Mains', 'Starters'])
        ->and($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(0);
});

it('moves a section down past the one below it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters'], 'position' => 0]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Mains'], 'position' => 1]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Desserts'], 'position' => 2]);

    $starters = MenuCategory::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Starters')->sole();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction(TestAction::make('moveDown')->table($starters));

    expect(sectionOrder($menu))->toBe(['Mains', 'Starters', 'Desserts']);
});

it('leaves the list alone when the top record is moved up', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $first = MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters'], 'position' => 0]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Mains'], 'position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction(TestAction::make('moveUp')->table($first));

    expect(sectionOrder($menu))->toBe(['Starters', 'Mains']);
});

it('sorts out positions that were all the same before swapping', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    // Seeds and imports leave duplicates behind; a swap between two records
    // both sitting at 0 would otherwise move nothing at all.
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters'], 'position' => 0]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Mains'], 'position' => 0]);

    $mains = MenuCategory::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Mains')->sole();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction(TestAction::make('moveUp')->table($mains));

    expect(sectionOrder($menu))->toBe(['Mains', 'Starters']);
});

it('never moves a section past one on another menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $lunch = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $dinner = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    MenuCategory::factory()->inMenu($lunch)->create(['name' => [Locale::English->value => 'Lunch starters'], 'position' => 0]);
    $dinnerFirst = MenuCategory::factory()->inMenu($dinner)->create(['name' => [Locale::English->value => 'Dinner starters'], 'position' => 0]);
    MenuCategory::factory()->inMenu($dinner)->create(['name' => [Locale::English->value => 'Dinner mains'], 'position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // A section is arranged within its own menu. Ordering across the whole
    // restaurant would let a tap on one menu shuffle another.
    Livewire::test(ListMenuCategories::class)
        ->callAction(TestAction::make('moveDown')->table($dinnerFirst));

    expect(sectionOrder($lunch))->toBe(['Lunch starters'])
        ->and(sectionOrder($dinner))->toBe(['Dinner mains', 'Dinner starters']);
});

it('arranges the home screen rows the same way', function (): void {
    $restaurant = Restaurant::factory()->create();

    $banner = HomeRow::factory()->ofRestaurant($restaurant)->titled('Banner')->create(['position' => 0]);
    $offers = HomeRow::factory()->ofRestaurant($restaurant)->titled('Offers')->create(['position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListHomeRows::class)
        ->callAction(TestAction::make('moveUp')->table($offers));

    expect($offers->refresh()->position)->toBe(0)
        ->and($banner->refresh()->position)->toBe(1);
});

it('keeps the arrows away from someone who may only read the menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $section = MenuCategory::factory()->inMenu($menu)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Staff);

    // The same permission a drag used to need: menu.manage, not menu.view.
    Livewire::test(ListMenuCategories::class)
        ->assertActionHidden(TestAction::make('moveUp')->table($section))
        ->assertActionHidden(TestAction::make('moveDown')->table($section));
});
