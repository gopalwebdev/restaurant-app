<?php

use App\Enums\Role as RoleEnum;
use App\Filament\Admin\Pages\Settings;
use App\Filament\Admin\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Admin\Resources\Menus\Pages\EditMenu;
use App\Filament\Admin\Resources\Menus\Pages\ListMenus;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCombo;
use App\Models\MenuComboItem;
use App\Models\MenuItem;
use App\Models\MenuSubCategory;
use App\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

/*
|--------------------------------------------------------------------------
| The panel's menu pages actually render
|--------------------------------------------------------------------------
|
| The tests elsewhere mount one relation manager or one table at a time, which
| says nothing about the page that holds four of them together. These two walk
| the pages a restaurant admin opens daily — once with a full tree on them, and
| once with nothing at all, because an empty restaurant is what every new one
| starts as and empty states are exactly where a missing relation or a null
| slips through.
|
*/

it('renders every menu page with a full tree on it', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->servedBetween('07:00', '11:00')->create(['tenant_id' => $restaurant->getKey()]);
    $category = MenuCategory::factory()->inMenu($menu)->create();
    $sub = MenuSubCategory::factory()->inCategory($category)->create();

    $direct = MenuItem::factory()->inCategory($category)->discounted()->create(['is_featured' => true]);
    $nested = MenuItem::factory()->inSubCategory($sub)->create();

    $combo = MenuCombo::factory()->onMenu($menu)->discounted()->create();
    MenuComboItem::factory()->pairing($combo, $direct)->quantity(2)->create();

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(ListMenus::class)->assertOk()->assertCanSeeTableRecords([$menu]);
    Livewire::test(EditMenu::class, ['record' => $menu->getKey()])->assertOk();
    Livewire::test(ListMenuItems::class)->assertOk()->assertCanSeeTableRecords([$direct, $nested]);
    Livewire::test(Settings::class)->assertOk();
});

it('renders the menu page for a restaurant with nothing on it yet', function (): void {
    $restaurant = Restaurant::factory()->create();
    $menu = Menu::factory()->create(['tenant_id' => $restaurant->getKey()]);

    enterRestaurantPanel($restaurant, RoleEnum::Admin);

    Livewire::test(EditMenu::class, ['record' => $menu->getKey()])->assertOk();
    Livewire::test(ListMenuItems::class)->assertOk();
});
