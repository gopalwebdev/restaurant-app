<?php

namespace App\Http\Controllers\Guest;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddition;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One of a restaurant's menus, read at the table.
 *
 * Four levels come down together — the menu, its sections, their dishes and
 * each dish's additions — because that is one screen a guest scrolls, and
 * fetching it in four passes would be four round trips for one page.
 *
 * Only what is actually orderable is sent: a hidden section, a sold-out dish
 * and an addition that has run out are all absent rather than greyed out,
 * because a guest reading a menu on a phone should not be scrolling past
 * things they cannot have.
 */
class MenuController extends Controller
{
    public function __invoke(Restaurant $restaurant, Menu $menu): Response
    {
        abort_unless($restaurant->is_active, 404);

        // The restaurant comes from the subdomain rather than the path, so
        // scoped bindings do not cover this and the check is made by hand.
        abort_unless($menu->restaurant_id === $restaurant->getKey(), 404);
        abort_unless($menu->is_active, 404);

        $currency = $restaurant->currency();

        $sections = MenuCategory::query()
            ->where('menu_id', $menu->getKey())
            ->active()
            ->with(['menuItems' => fn ($items) => $items
                ->where('is_available', true)
                ->with(['additions' => fn ($additions) => $additions->available()->inMenuOrder()])
                ->inMenuOrder()])
            ->inMenuOrder()
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->menuItems->isNotEmpty());

        return Inertia::render('menu', [
            'menu' => [
                'id' => $menu->getKey(),
                'name' => $menu->name,
                'description' => $menu->description,
            ],
            'sections' => $sections->map(fn (MenuCategory $category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'items' => $category->menuItems->map(
                    fn (MenuItem $item): array => $this->presentItem($item, $currency),
                )->values()->all(),
            ])->values()->all(),
            'acceptingOrders' => $restaurant->isAcceptingOrders(),
            'homeUrl' => route('guest.home', ['restaurant' => $restaurant->slug]),
        ]);
    }

    /**
     * One dish and the extras it can be ordered with.
     *
     * The currency is passed down rather than read per dish: every price on a
     * menu shares one, and asking the model each time is a query per row that
     * answers the same question.
     *
     * @return array<string, mixed>
     */
    private function presentItem(MenuItem $item, Currency $currency): array
    {
        return [
            'id' => $item->getKey(),
            'name' => $item->name,
            'description' => $item->description,
            'price' => $item->formattedPrice($currency),
            'foodType' => $item->food_type->value,
            'additions' => $item->additions->map(fn (MenuItemAddition $addition): array => [
                'id' => $addition->getKey(),
                'name' => $addition->name,
                // A free addition is sent as null rather than "₹0.00", which
                // reads as a mistake beside a choice that simply costs nothing.
                'price' => $addition->isFree() ? null : $addition->formattedPrice($currency),
            ])->values()->all(),
        ];
    }
}
