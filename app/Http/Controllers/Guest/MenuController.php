<?php

namespace App\Http\Controllers\Guest;

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
 * fetching it in four passes would be four round trips for one page. Each
 * query names the columns it needs, so a long menu does not carry timestamps
 * and foreign keys nobody renders.
 *
 * Only what is actually orderable is sent: a hidden section, a sold-out dish
 * and an addition that has run out are all absent rather than greyed out,
 * because a guest reading a menu on a phone should not be scrolling past
 * things they cannot have.
 *
 * Prices go out as integers. Turning 24950 into ₹249.50 happens in the browser
 * — see resources/js/lib/money.ts — so the server never builds a string per row
 * and the result follows the guest's own language.
 */
class MenuController extends Controller
{
    public function __invoke(Restaurant $restaurant, Menu $menu): Response
    {
        abort_unless($restaurant->is_active, 404);

        // The restaurant comes from the subdomain rather than the path, so
        // scoped bindings do not cover this and the check is made by hand.
        abort_unless($menu->tenant_id === $restaurant->getKey(), 404);
        abort_unless($menu->is_active, 404);

        $sections = MenuCategory::query()
            ->select(['id', 'name'])
            ->where('menu_id', $menu->getKey())
            ->active()
            ->with(['menuItems' => fn ($items) => $items
                ->select(['id', 'menu_category_id', 'name', 'description', 'price_minor_units', 'food_type'])
                ->where('is_available', true)
                ->with(['additions' => fn ($additions) => $additions
                    ->select(['id', 'menu_item_id', 'name', 'price_minor_units'])
                    ->available()
                    ->inMenuOrder()])
                ->inMenuOrder()])
            ->inMenuOrder()
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->menuItems->isNotEmpty());

        // The dishes this menu leads with, above its sections. A separate query
        // rather than a flag read off the sections above: featuring has its own
        // order, and the same dish appears again under its section — a guest
        // scrolling down should find it where they expect it.
        $featured = MenuItem::query()
            ->select(['id', 'menu_category_id', 'name', 'description', 'price_minor_units', 'food_type'])
            ->where('tenant_id', $restaurant->getKey())
            ->featuredOnMenu($menu->getKey())
            ->orderable()
            ->with(['additions' => fn ($additions) => $additions
                ->select(['id', 'menu_item_id', 'name', 'price_minor_units'])
                ->available()
                ->inMenuOrder()])
            ->inFeaturedOrder()
            ->get();

        return Inertia::render('menu', [
            'menu' => [
                'id' => $menu->getKey(),
                'name' => $menu->name,
                'description' => $menu->description,
            ],
            'featured' => $featured->map(
                fn (MenuItem $item): array => $this->presentItem($item),
            )->values()->all(),
            'sections' => $sections->map(fn (MenuCategory $category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'items' => $category->menuItems->map(
                    fn (MenuItem $item): array => $this->presentItem($item),
                )->values()->all(),
            ])->values()->all(),
            'acceptingOrders' => $restaurant->isAcceptingOrders(),
            'homeUrl' => route('guest.home', ['restaurant' => $restaurant->slug]),
        ]);
    }

    /**
     * One dish and the extras it can be ordered with.
     *
     * @return array<string, mixed>
     */
    private function presentItem(MenuItem $item): array
    {
        return [
            'id' => $item->getKey(),
            'name' => $item->name,
            'description' => $item->description,
            'priceMinorUnits' => $item->price_minor_units,
            'foodType' => $item->food_type->value,
            'additions' => $item->additions->map(fn (MenuItemAddition $addition): array => [
                'id' => $addition->getKey(),
                'name' => $addition->name,
                // Zero is a real price, and the guest app says "Free" rather
                // than "+ ₹0.00" — which reads as a mistake.
                'priceMinorUnits' => $addition->price_minor_units,
            ])->values()->all(),
        ];
    }
}
