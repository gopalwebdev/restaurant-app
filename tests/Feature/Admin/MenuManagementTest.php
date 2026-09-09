<?php

use App\Enums\Currency;
use App\Enums\FoodType;
use App\Enums\Locale;
use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Admin\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Admin\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
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

/**
 * Find a record by the English half of its translated name.
 *
 * Every unique index in the schema is built on this value, and so is every
 * lookup here — matching the whole JSON document would only find a record whose
 * every language happened to agree.
 *
 * @param  class-string<Menu|MenuCategory|MenuItem|MenuItemAddition>  $model
 */
function byEnglishName(string $model, string $name): Menu|MenuCategory|MenuItem|MenuItemAddition
{
    return $model::query()
        ->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, $name)
        ->sole();
}

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
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory(MenuCategory::factory()->inMenu($menu)->create())->create();

    $admin = enterRestaurantPanel($restaurant, RoleEnum::Admin);

    expect($admin->can('viewAny', MenuItem::class))->toBeTrue()
        ->and($admin->can('create', MenuItem::class))->toBeTrue()
        ->and($admin->can('update', $item))->toBeTrue()
        ->and($admin->can('delete', $item))->toBeTrue()
        ->and($admin->can('viewAny', Menu::class))->toBeTrue()
        ->and($admin->can('create', Menu::class))->toBeTrue()
        ->and($admin->can('reorder', Menu::class))->toBeTrue();
});

it('lets staff read the menu but not change it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $item = MenuItem::factory()->inCategory(MenuCategory::factory()->inMenu($menu)->create())->create();

    $staff = enterRestaurantPanel($restaurant, RoleEnum::Staff);

    expect($staff->can(PermissionEnum::MenuView->value))->toBeTrue()
        ->and($staff->can('viewAny', MenuItem::class))->toBeTrue()
        ->and($staff->can('viewAny', Menu::class))->toBeTrue()
        ->and($staff->can('create', MenuItem::class))->toBeFalse()
        ->and($staff->can('create', Menu::class))->toBeFalse()
        ->and($staff->can('update', $item))->toBeFalse()
        ->and($staff->can('delete', $item))->toBeFalse();
});

it('keeps someone with no role off the menu pages', function (): void {
    $restaurant = Restaurant::factory()->create();
    $nobody = User::factory()->ofRestaurant($restaurant)->create();

    expect($nobody->can('viewAny', MenuItem::class))->toBeFalse()
        ->and($nobody->can('viewAny', MenuCategory::class))->toBeFalse()
        ->and($nobody->can('viewAny', Menu::class))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Menus
|--------------------------------------------------------------------------
*/

it('creates a menu against the restaurant whose panel it is', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenus::class)
        ->callAction('create', [
            'name' => [Locale::English->value => 'Dinner', Locale::Tamil->value => 'இரவு உணவு'],
            'position' => 1,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $menu = byEnglishName(Menu::class, 'Dinner');

    expect($menu->restaurant_id)->toBe($restaurant->getKey())
        ->and($menu->is_active)->toBeTrue()
        ->and($menu->getTranslations('name'))->toBe([
            Locale::English->value => 'Dinner',
            Locale::Tamil->value => 'இரவு உணவு',
        ]);
});

it('lets a menu be created in English alone', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // A restaurant that has not translated its menu yet is the normal state on
    // day one, so only the fallback language is required.
    Livewire::test(ListMenus::class)
        ->callAction('create', ['name' => [Locale::English->value => 'Lunch'], 'position' => 0, 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(byEnglishName(Menu::class, 'Lunch')->name)->toBe('Lunch');
});

it('requires the fallback language on a menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Tamil alone would leave an English-reading guest with a blank heading,
    // and there would be nothing for the unique index to be built on.
    Livewire::test(ListMenus::class)
        ->callAction('create', ['name' => [Locale::Tamil->value => 'இரவு உணவு'], 'position' => 0, 'is_active' => true])
        ->assertHasActionErrors(['name.'.Locale::English->value]);
});

it('refuses a menu name the restaurant already uses', function (): void {
    $restaurant = Restaurant::factory()->create();
    Menu::factory()->create([
        'restaurant_id' => $restaurant->getKey(),
        'name' => [Locale::English->value => 'Dinner'],
    ]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenus::class)
        ->callAction('create', ['name' => [Locale::English->value => 'Dinner'], 'position' => 0, 'is_active' => true])
        ->assertHasActionErrors(['name.'.Locale::English->value]);
});

it('takes a menu\'s sections and their dishes with it when it is deleted', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    $item = MenuItem::factory()->inCategory($category)->create();
    $addition = MenuItemAddition::factory()->onItem($item)->create();

    $menu->delete();

    expect(MenuCategory::query()->whereKey($category->getKey())->exists())->toBeFalse()
        ->and(MenuItem::query()->whereKey($item->getKey())->exists())->toBeFalse()
        ->and(MenuItemAddition::query()->whereKey($addition->getKey())->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Sections
|--------------------------------------------------------------------------
*/

it('creates a section on the menu it was filed under', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', [
            'menu_id' => $menu->getKey(),
            'name' => [Locale::English->value => 'Starters', Locale::Tamil->value => 'தொடக்கங்கள்'],
            'position' => 1,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $category = byEnglishName(MenuCategory::class, 'Starters');

    expect($category->restaurant_id)->toBe($restaurant->getKey())
        ->and($category->menu_id)->toBe($menu->getKey())
        ->and($category->is_active)->toBeTrue()
        ->and($category->getTranslation('name', Locale::Tamil->value))->toBe('தொடக்கங்கள்');
});

it('refuses a section name the same menu already uses', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Starters']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', [
            'menu_id' => $menu->getKey(),
            'name' => [Locale::English->value => 'Starters'],
            'position' => 0,
            'is_active' => true,
        ])
        ->assertHasActionErrors(['name.'.Locale::English->value]);
});

it('lets a lunch and a dinner menu each have their own Starters', function (): void {
    $restaurant = Restaurant::factory()->create();
    $lunch = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $dinner = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    MenuCategory::factory()->inMenu($lunch)->create(['name' => [Locale::English->value => 'Starters']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Uniqueness moved from the restaurant to the menu when menus arrived, and
    // this is the case that motivated it.
    Livewire::test(ListMenuCategories::class)
        ->callAction('create', [
            'menu_id' => $dinner->getKey(),
            'name' => [Locale::English->value => 'Starters'],
            'position' => 0,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(MenuCategory::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Starters')
        ->count())->toBe(2);
});

it('lets two restaurants both have a section of the same name', function (): void {
    $other = Restaurant::factory()->create();
    MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $other->getKey()]))
        ->create(['name' => [Locale::English->value => 'Starters']]);

    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuCategories::class)
        ->callAction('create', [
            'menu_id' => $menu->getKey(),
            'name' => [Locale::English->value => 'Starters'],
            'position' => 0,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    // The panel is booted, so MenuCategory carries a tenancy scope. Counting
    // across restaurants has to step outside it deliberately.
    expect(MenuCategory::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Starters')
        ->count())->toBe(2);
});

it('refuses at the database to file a section under another restaurant\'s menu', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $theirMenu = Menu::factory()->create(['restaurant_id' => $theirs->getKey()]);

    expect(fn () => DB::table('menu_categories')->insert([
        'restaurant_id' => $mine->getKey(),
        'menu_id' => $theirMenu->getKey(),
        'name' => json_encode([Locale::English->value => 'Smuggled'], JSON_THROW_ON_ERROR),
        'position' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('groups sections by their menu without ordering on the translated json column', function (): void {
    // menu.name and menu_categories.name are both translated json columns.
    // Postgres has no ordering operator for json, so a group whose default
    // ordering selects one 500s there even though SQLite — what this suite
    // runs against — tolerates it silently. Inspecting the compiled SQL
    // catches that regardless of which database is running.
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    MenuCategory::factory()->inMenu($menu)->count(2)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    $group = Livewire::test(ListMenuCategories::class)
        ->assertOk()
        ->instance()
        ->getTable()
        ->getDefaultGroup();

    $sql = $group->orderQuery(MenuCategory::query(), 'asc')->toSql();

    expect($sql)->toContain('position')
        ->and($sql)->not->toContain('name');
});

/*
|--------------------------------------------------------------------------
| Dishes, and the money they are priced in
|--------------------------------------------------------------------------
*/

it('stores a typed price as an exact integer count of minor units', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update(['currency' => Currency::IndianRupee]);
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => [Locale::English->value => 'Paneer Tikka'],
            'menu_category_id' => $category->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '249.50',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasNoActionErrors();

    $item = byEnglishName(MenuItem::class, 'Paneer Tikka');

    // ₹249.50 is 24950 paise, exactly. No float ever reaches the column.
    expect($item->price_minor_units)->toBe(24950)
        ->and($item->price_minor_units)->toBeInt()
        ->and($item->formattedPrice())->toBe('₹249.50')
        ->and($item->restaurant_id)->toBe($restaurant->getKey());
});

it('round-trips a price through the edit form without drift', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();
    $item = MenuItem::factory()->inCategory($category)->create(['price_minor_units' => 24950]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->callAction(TestAction::make('edit')->table($item), [
            'name' => $item->getTranslations('name'),
            'menu_category_id' => $category->getKey(),
            'food_type' => $item->food_type->value,
            'price' => '249.50',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasNoActionErrors();

    expect($item->refresh()->price_minor_units)->toBe(24950);
});

it('fills the edit form with every language, not just the current one', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();
    $item = MenuItem::factory()->inCategory($category)->create([
        'name' => [Locale::English->value => 'Paneer Tikka', Locale::Tamil->value => 'பன்னீர் டிக்கா'],
    ]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Spatie hands back one language for a translated attribute; the form edits
    // them all, so the whole document has to be put back before filling.
    Livewire::test(ListMenuItems::class)
        ->mountAction(TestAction::make('edit')->table($item))
        ->assertActionDataSet([
            'name' => [Locale::English->value => 'Paneer Tikka', Locale::Tamil->value => 'பன்னீர் டிக்கா'],
        ]);
});

it('formats a price in rupees', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();
    $item = MenuItem::factory()->inCategory($category)->create(['price_minor_units' => 1250]);

    expect($item->formattedPrice())->toBe('₹12.50');
});

it('groups dishes by their section and by their menu without ordering on json', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    MenuItem::factory()->inCategory($category)->count(2)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    $table = Livewire::test(ListMenuItems::class)->assertOk()->instance()->getTable();

    $bySection = $table->getDefaultGroup();
    $sectionSql = $bySection->orderQuery(MenuItem::query(), 'asc')->toSql();

    expect($sectionSql)->toContain('position')
        ->and($sectionSql)->not->toContain('name');

    $byMenu = $table->getGroup('menuCategory.menu_id');
    $menuSql = $byMenu->orderQuery(MenuItem::query(), 'asc')->toSql();

    expect($menuSql)->toContain('position')
        ->and($menuSql)->not->toContain('name');
});

/*
|--------------------------------------------------------------------------
| Additions
|--------------------------------------------------------------------------
*/

it('saves a dish\'s additions in the same save as the dish', function (): void {
    $restaurant = Restaurant::factory()->create();
    $restaurant->settings->update(['currency' => Currency::IndianRupee]);
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => [Locale::English->value => 'Paneer Tikka'],
            'menu_category_id' => $category->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '249.50',
            'is_available' => true,
            'position' => 0,
            'additions' => [
                [
                    'name' => [Locale::English->value => 'Extra paneer', Locale::Tamil->value => 'கூடுதல் பன்னீர்'],
                    'price' => '50',
                    'is_available' => true,
                ],
                [
                    'name' => [Locale::English->value => 'Less spicy'],
                    'price' => '0',
                    'is_available' => true,
                ],
            ],
        ])
        ->assertHasNoActionErrors();

    $item = byEnglishName(MenuItem::class, 'Paneer Tikka');
    $additions = $item->additions()->inMenuOrder()->get();

    expect($additions)->toHaveCount(2);

    $extra = byEnglishName(MenuItemAddition::class, 'Extra paneer');
    $free = byEnglishName(MenuItemAddition::class, 'Less spicy');

    expect($extra->price_minor_units)->toBe(5000)
        ->and($extra->restaurant_id)->toBe($restaurant->getKey())
        ->and($extra->menu_item_id)->toBe($item->getKey())
        ->and($extra->getTranslation('name', Locale::Tamil->value))->toBe('கூடுதல் பன்னீர்')
        // Zero is a real price: "no onions" costs nothing and is still listed.
        ->and($free->price_minor_units)->toBe(0)
        ->and($free->isFree())->toBeTrue();
});

it('lets a dish be saved with no additions at all', function (): void {
    $restaurant = Restaurant::factory()->create();
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Most dishes have none, so the repeater must not start with a blank row
    // that then fails validation.
    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => [Locale::English->value => 'Tandoori Roti'],
            'menu_category_id' => $category->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '50',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasNoActionErrors();

    expect(byEnglishName(MenuItem::class, 'Tandoori Roti')->additions)->toBeEmpty();
});

it('takes a dish\'s additions with it when it is deleted', function (): void {
    $restaurant = Restaurant::factory()->create();
    $item = MenuItem::factory()->inCategory(
        MenuCategory::factory()->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))->create(),
    )->create();
    $addition = MenuItemAddition::factory()->onItem($item)->create();

    $item->delete();

    expect(MenuItemAddition::query()->whereKey($addition->getKey())->exists())->toBeFalse();
});

it('refuses at the database to hang an addition off another restaurant\'s dish', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $theirItem = MenuItem::factory()->inCategory(
        MenuCategory::factory()->inMenu(Menu::factory()->create(['restaurant_id' => $theirs->getKey()]))->create(),
    )->create();

    expect(fn () => DB::table('menu_item_additions')->insert([
        'restaurant_id' => $mine->getKey(),
        'menu_item_id' => $theirItem->getKey(),
        'name' => json_encode([Locale::English->value => 'Smuggled'], JSON_THROW_ON_ERROR),
        'price_minor_units' => 1000,
        'is_available' => true,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| One restaurant never reaches another's menu
|--------------------------------------------------------------------------
*/

it('shows only this restaurant\'s dishes', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $myItem = MenuItem::factory()->inCategory(
        MenuCategory::factory()->inMenu(Menu::factory()->create(['restaurant_id' => $mine->getKey()]))->create(),
    )->create();

    $theirItem = MenuItem::factory()->inCategory(
        MenuCategory::factory()->inMenu(Menu::factory()->create(['restaurant_id' => $theirs->getKey()]))->create(),
    )->create();

    enterRestaurantPanel($mine, RoleEnum::Admin);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords([$myItem])
        ->assertCanNotSeeTableRecords([$theirItem]);
});

it('shows only this restaurant\'s menus', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $myMenu = Menu::factory()->create(['restaurant_id' => $mine->getKey()]);
    $theirMenu = Menu::factory()->create(['restaurant_id' => $theirs->getKey()]);

    enterRestaurantPanel($mine, RoleEnum::Admin);

    Livewire::test(ListMenus::class)
        ->assertCanSeeTableRecords([$myMenu])
        ->assertCanNotSeeTableRecords([$theirMenu]);
});

it('refuses to file a dish under another restaurant\'s section', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    MenuCategory::factory()->inMenu(Menu::factory()->create(['restaurant_id' => $mine->getKey()]))->create();
    $theirCategory = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $theirs->getKey()]))
        ->create();

    enterRestaurantPanel($mine, RoleEnum::Admin);

    // Only this restaurant's sections are offered, and Filament validates the
    // submitted value against that list — so a tampered id is rejected here,
    // before the composite foreign key would have refused the row anyway.
    Livewire::test(ListMenuItems::class)
        ->callAction('create', [
            'name' => [Locale::English->value => 'Smuggled'],
            'menu_category_id' => $theirCategory->getKey(),
            'food_type' => FoodType::Vegetarian->value,
            'price' => '100',
            'is_available' => true,
            'position' => 0,
        ])
        ->assertHasActionErrors(['menu_category_id']);

    expect(MenuItem::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Smuggled')
        ->exists())->toBeFalse();
});

it('refuses at the database to file a dish under another restaurant\'s section', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $theirCategory = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $theirs->getKey()]))
        ->create();

    // The composite foreign key is the guarantee behind the tenant scope: even
    // a tampered request cannot store a dish pointing across restaurants.
    expect(fn () => DB::table('menu_items')->insert([
        'restaurant_id' => $mine->getKey(),
        'menu_category_id' => $theirCategory->getKey(),
        'name' => json_encode([Locale::English->value => 'Smuggled'], JSON_THROW_ON_ERROR),
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
    $category = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]))
        ->create();
    $item = MenuItem::factory()->inCategory($category)->create();

    $category->delete();

    expect(MenuItem::query()->whereKey($item->getKey())->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| What a guest may actually order
|--------------------------------------------------------------------------
*/

it('counts an item orderable only when it, its section and its menu are showing', function (): void {
    $restaurant = Restaurant::factory()->create();

    $menu = Menu::factory()->create(['restaurant_id' => $restaurant->getKey()]);
    $hiddenMenu = Menu::factory()->hidden()->create(['restaurant_id' => $restaurant->getKey()]);

    $showing = MenuCategory::factory()->inMenu($menu)->create();
    $hidden = MenuCategory::factory()->inMenu($menu)->hidden()->create();
    $onHiddenMenu = MenuCategory::factory()->inMenu($hiddenMenu)->create();

    $orderable = MenuItem::factory()->inCategory($showing)->create();
    $soldOut = MenuItem::factory()->inCategory($showing)->unavailable()->create();
    $inHiddenSection = MenuItem::factory()->inCategory($hidden)->create();
    $inHiddenMenu = MenuItem::factory()->inCategory($onHiddenMenu)->create();

    $names = MenuItem::query()->orderable()->get()->pluck('name')->all();

    expect($names)->toContain($orderable->name)
        ->and($names)->not->toContain($soldOut->name)
        ->and($names)->not->toContain($inHiddenSection->name)
        // Hiding a whole menu has to take everything under it down too.
        ->and($names)->not->toContain($inHiddenMenu->name);
});
