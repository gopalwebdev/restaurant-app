<?php

use App\Actions\Menus\MoveCategoryToMenu;
use App\Actions\Menus\MoveSubCategoryToCategory;
use App\Enums\Locale;
use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Admin\Resources\Menus\Pages\EditMenu;
use App\Filament\Admin\Resources\Menus\RelationManagers\SubCategoriesRelationManager;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubCategory;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LogicException;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Find a sub-category by the English half of its translated name.
 */
function subCategoryNamed(string $name): MenuSubCategory
{
    return MenuSubCategory::query()
        ->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, $name)
        ->sole();
}

/**
 * Open the sub-categories table on one menu's page.
 */
function subCategoriesOf(Menu $menu): Testable
{
    return Livewire::test(SubCategoriesRelationManager::class, [
        'ownerRecord' => $menu,
        'pageClass' => EditMenu::class,
    ]);
}

/*
|--------------------------------------------------------------------------
| Subdividing a category
|--------------------------------------------------------------------------
|
| The fifth level of the menu, and an optional one: a restaurant that does not
| subdivide anything never creates one.
|
*/

it('subdivides a category from the menu page', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    subCategoriesOf($menu)
        ->callAction(TestAction::make('create')->table(), [
            'menu_category_id' => $category->getKey(),
            'name' => [Locale::English->value => 'Chicken', Locale::Tamil->value => 'சிக்கன்'],
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $subCategory = subCategoryNamed('Chicken');

    // tenant_id is derived from the category rather than typed: the relation
    // manager writes through a HasManyThrough, which sets neither half.
    expect($subCategory->tenant_id)->toBe($restaurant->getKey())
        ->and($subCategory->menu_category_id)->toBe($category->getKey())
        ->and($subCategory->getTranslation('name', Locale::Tamil->value))->toBe('சிக்கன்');
});

it('refuses a sub-category name the same category already uses', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    MenuSubCategory::factory()->inCategory($category)->create(['name' => [Locale::English->value => 'Chicken']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    subCategoriesOf($menu)
        ->callAction(TestAction::make('create')->table(), [
            'menu_category_id' => $category->getKey(),
            'name' => [Locale::English->value => 'Chicken'],
            'is_active' => true,
        ])
        ->assertHasActionErrors(['name.'.Locale::English->value]);
});

it('lets two categories of one menu each have their own Chicken', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $biryani = MenuCategory::factory()->inMenu($menu)->create();
    $curries = MenuCategory::factory()->inMenu($menu)->create();

    MenuSubCategory::factory()->inCategory($biryani)->create(['name' => [Locale::English->value => 'Chicken']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Uniqueness is per category, matching the expression index — this is the
    // case that decides it is not per menu.
    subCategoriesOf($menu)
        ->callAction(TestAction::make('create')->table(), [
            'menu_category_id' => $curries->getKey(),
            'name' => [Locale::English->value => 'Chicken'],
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(MenuSubCategory::query()->withoutGlobalScopes()
        ->where('name->'.Locale::English->value, 'Chicken')
        ->count())->toBe(2);
});

it('refuses at the database to subdivide another restaurant\'s category', function (): void {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();
    $theirCategory = MenuCategory::factory()
        ->inMenu(Menu::factory()->create(['tenant_id' => $theirs->getKey()]))
        ->create();

    expect(fn () => DB::table('menu_sub_categories')->insert([
        'tenant_id' => $mine->getKey(),
        'menu_category_id' => $theirCategory->getKey(),
        'name' => json_encode([Locale::English->value => 'Smuggled'], JSON_THROW_ON_ERROR),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| Filing a dish under one
|--------------------------------------------------------------------------
*/

it('refuses at the database a dish naming a sub-category of another category', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $biryani = MenuCategory::factory()->inMenu($menu)->create();
    $curries = MenuCategory::factory()->inMenu($menu)->create();
    $chicken = MenuSubCategory::factory()->inCategory($biryani)->create();
    $dish = MenuItem::factory()->inCategory($curries)->create();

    // The composite key references (id, menu_category_id), so the pair cannot
    // drift even though both rows belong to this restaurant.
    expect(fn () => DB::table('menu_items')
        ->where('id', $dish->getKey())
        ->update(['menu_sub_category_id' => $chicken->getKey()]))
        ->toThrow(QueryException::class);
});

it('leaves a dish filed straight under its category alone', function (): void {
    $category = MenuCategory::factory()->create();
    $dish = MenuItem::factory()->inCategory($category)->create();

    // A null sub-category means the composite key is not evaluated at all,
    // which is how most dishes pass it.
    expect($dish->menu_sub_category_id)->toBeNull()
        ->and($category->directMenuItems()->pluck('id')->all())->toBe([$dish->getKey()]);
});

it('lists a dish under its sub-category in the panel tree', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create(['name' => [Locale::English->value => 'Biryani']]);
    $chicken = MenuSubCategory::factory()->inCategory($category)->create(['name' => [Locale::English->value => 'Chicken']]);

    $inSub = MenuItem::factory()->inSubCategory($chicken)->create();
    $direct = MenuItem::factory()->inCategory($category)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    $group = Livewire::test(ListMenuItems::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$inSub, $direct])
        ->instance()
        ->getTable()
        ->getDefaultGroup();

    // The heading is the branch, not just the category: a composite key is the
    // only thing that can tell "Biryani" from "Biryani › Chicken", because
    // neither column answers on its own.
    expect($group->getTitle($inSub->fresh()->load(['menuCategory', 'menuSubCategory'])))
        ->toBe('Biryani › Chicken')
        ->and($group->getTitle($direct->fresh()->load(['menuCategory', 'menuSubCategory'])))
        ->toBe('Biryani');
});

it('groups the dishes tree without ordering on a translated json column', function (): void {
    // Postgres has no ordering operator for json, so ordering a group by a
    // translated name 500s there even though SQLite tolerates it silently.
    // Inspecting the compiled SQL catches that on either database.
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    MenuItem::factory()->inCategory(MenuCategory::factory()->inMenu($menu)->create())->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    $group = Livewire::test(ListMenuItems::class)
        ->instance()
        ->getTable()
        ->getDefaultGroup();

    $sql = $group->orderQuery(MenuItem::query(), 'asc')->toSql();

    expect($sql)->toContain('position')
        ->and($sql)->not->toContain('name');
});

/*
|--------------------------------------------------------------------------
| Moving one between categories
|--------------------------------------------------------------------------
*/

it('moves a sub-category to another category of the same menu, dishes and all', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $from = MenuCategory::factory()->inMenu($menu)->create();
    $to = MenuCategory::factory()->inMenu($menu)->create();
    $chicken = MenuSubCategory::factory()->inCategory($from)->create();

    $moving = MenuItem::factory()->inSubCategory($chicken)->create();
    $staying = MenuItem::factory()->inCategory($from)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    subCategoriesOf($menu)
        ->callAction(TestAction::make('moveToCategory')->table($chicken), ['menu_category_id' => $to->getKey()])
        ->assertHasNoActionErrors();

    // The dish under it is carried across by the ON UPDATE CASCADE on
    // menu_items — there is no order of two statements that would be legal at
    // every step, which is why the cascade exists.
    expect($chicken->refresh()->menu_category_id)->toBe($to->getKey())
        ->and($moving->refresh()->menu_category_id)->toBe($to->getKey())
        ->and($moving->menu_sub_category_id)->toBe($chicken->getKey())
        // A dish filed straight under the old category is not part of the
        // branch and does not move.
        ->and($staying->refresh()->menu_category_id)->toBe($from->getKey());
});

it('offers only the categories of this menu to move a sub-category to', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $otherMenu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $from = MenuCategory::factory()->inMenu($menu)->create();
    $sibling = MenuCategory::factory()->inMenu($menu)->create();
    $elsewhere = MenuCategory::factory()->inMenu($otherMenu)->create();

    $chicken = MenuSubCategory::factory()->inCategory($from)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    subCategoriesOf($menu)
        ->mountAction(TestAction::make('moveToCategory')->table($chicken))
        ->assertSchemaComponentExists('menu_category_id', checkComponentUsing: function ($component) use ($from, $sibling, $elsewhere): bool {
            $offered = array_keys($component->getOptions());

            expect($offered)->toContain($sibling->getKey())
                ->and($offered)->not->toContain($from->getKey())
                // A sub-category never changes menus, so a category on another
                // menu is not on offer at all.
                ->and($offered)->not->toContain($elsewhere->getKey());

            return true;
        });
});

it('refuses to carry a sub-category onto another menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $lunch = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $dinner = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $chicken = MenuSubCategory::factory()
        ->inCategory(MenuCategory::factory()->inMenu($lunch)->create())
        ->create();
    $target = MenuCategory::factory()->inMenu($dinner)->create();

    // The composite foreign key would allow this — both categories belong to
    // the restaurant — so the action is the only thing standing in the way.
    app(MoveSubCategoryToCategory::class)($chicken, $target);
})->throws(LogicException::class, 'one menu');

it('refuses a move onto a category that already has that name', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $from = MenuCategory::factory()->inMenu($menu)->create();
    $to = MenuCategory::factory()->inMenu($menu)->create();

    $moving = MenuSubCategory::factory()->inCategory($from)->create(['name' => [Locale::English->value => 'Chicken']]);
    MenuSubCategory::factory()->inCategory($to)->create(['name' => [Locale::English->value => 'Chicken']]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    // Caught as validation rather than at the expression index, which would
    // otherwise reject the update after the form had already passed.
    subCategoriesOf($menu)
        ->callAction(TestAction::make('moveToCategory')->table($moving), ['menu_category_id' => $to->getKey()])
        ->assertHasActionErrors(['menu_category_id']);

    expect($moving->refresh()->menu_category_id)->toBe($from->getKey());
});

it('carries a category\'s sub-categories with it onto another menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $lunch = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $dinner = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    $category = MenuCategory::factory()->inMenu($lunch)->create();
    $chicken = MenuSubCategory::factory()->inCategory($category)->create();

    app(MoveCategoryToMenu::class)($category, $dinner);

    // A sub-category has no menu column: it follows because it hangs off the
    // category. Moving the category is the only way one ever changes menus.
    expect($chicken->refresh()->menu_category_id)->toBe($category->getKey())
        ->and($category->refresh()->menu_id)->toBe($dinner->getKey());
});

/*
|--------------------------------------------------------------------------
| Hiding and deleting
|--------------------------------------------------------------------------
*/

it('takes a hidden sub-category\'s dishes off the menu, and nothing else\'s', function (): void {
    $category = MenuCategory::factory()->create();
    $hidden = MenuSubCategory::factory()->inCategory($category)->hidden()->create();
    $showing = MenuSubCategory::factory()->inCategory($category)->create();

    $inHidden = MenuItem::factory()->inSubCategory($hidden)->create();
    $inShowing = MenuItem::factory()->inSubCategory($showing)->create();
    $direct = MenuItem::factory()->inCategory($category)->create();

    $orderable = MenuItem::query()->orderable()->pluck('id')->all();

    expect($orderable)->toContain($inShowing->getKey())
        // A dish with no sub-category at all must not be swept up by the
        // clause that hides the ones in a hidden subdivision.
        ->toContain($direct->getKey())
        ->not->toContain($inHidden->getKey());
});

it('takes a sub-category\'s dishes with it when it is deleted', function (): void {
    $category = MenuCategory::factory()->create();
    $chicken = MenuSubCategory::factory()->inCategory($category)->create();

    $inSub = MenuItem::factory()->inSubCategory($chicken)->create();
    $direct = MenuItem::factory()->inCategory($category)->create();

    $chicken->delete();

    expect(MenuItem::query()->withoutGlobalScopes()->find($inSub->getKey()))->toBeNull()
        ->and(MenuItem::query()->withoutGlobalScopes()->find($direct->getKey()))->not->toBeNull();
});

it('takes its sub-categories with a category when that is deleted', function (): void {
    $category = MenuCategory::factory()->create();
    $chicken = MenuSubCategory::factory()->inCategory($category)->create();

    $category->delete();

    expect(MenuSubCategory::query()->withoutGlobalScopes()->find($chicken->getKey()))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Rearranging
|--------------------------------------------------------------------------
*/

it('rearranges sub-categories by dragging them', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    $first = MenuSubCategory::factory()->inCategory($category)->create(['position' => 0]);
    $second = MenuSubCategory::factory()->inCategory($category)->create(['position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    subCategoriesOf($menu)->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($second->refresh()->position)->toBeLessThan($first->refresh()->position);
});

it('keeps rearranging sub-categories away from someone who may only read the menu', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();

    $first = MenuSubCategory::factory()->inCategory($category)->create(['position' => 0]);
    $second = MenuSubCategory::factory()->inCategory($category)->create(['position' => 1]);

    enterRestaurantPanel($restaurant, RoleEnum::Staff);

    // reorderTable() short-circuits on the reorder() policy method, so
    // asserting the button is hidden would prove nothing.
    subCategoriesOf($menu)->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($first->refresh()->position)->toBe(0)
        ->and($second->refresh()->position)->toBe(1);
});

it('keeps another restaurant\'s sub-categories out of the table', function (): void {
    $mine = Restaurant::factory()->create();
    $myMenu = Menu::factory()->create(['tenant_id' => $mine->getKey()]);
    $myCategory = MenuCategory::factory()->inMenu($myMenu)->create();
    $ours = MenuSubCategory::factory()->inCategory($myCategory)->create();

    $theirs = Restaurant::factory()->create();
    $theirSub = MenuSubCategory::factory()
        ->inCategory(MenuCategory::factory()->inMenu(
            Menu::factory()->create(['tenant_id' => $theirs->getKey()])
        )->create())
        ->create();

    enterRestaurantPanel($mine, RoleEnum::Admin);

    subCategoriesOf($myMenu)
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirSub]);
});
