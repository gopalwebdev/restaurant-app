<?php

namespace App\Http\Controllers\Staff;

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
 * What a staff member sees once they are in.
 *
 * Orders are the point of this app and do not exist yet, so for now it shows
 * the floor what is on and what has sold out — which is the other thing they
 * are asked at a table all evening, and is real rather than a placeholder.
 *
 * Read top down as menus-with-sections-with-dishes, one query shape shared with
 * the guest menu. Unlike the guest menu, nothing is filtered out: hidden menus,
 * hidden sections, sold-out dishes and unavailable additions are all here and
 * all marked, because staff are asked about them all evening and "we have run
 * out" is an answer they need to be able to give.
 */
class HomeController extends Controller
{
    public function __invoke(Restaurant $restaurant): Response
    {
        $currency = $restaurant->currency();

        $menus = Menu::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->with(['menuCategories' => fn ($categories) => $categories
                ->with(['menuItems' => fn ($items) => $items
                    ->with(['additions' => fn ($additions) => $additions->inMenuOrder()])
                    ->inMenuOrder()])
                ->inMenuOrder()])
            ->inMenuOrder()
            ->get();

        return Inertia::render('home', [
            'menus' => $menus->map(fn (Menu $menu): array => [
                'id' => $menu->getKey(),
                'name' => $menu->name,
                'isActive' => $menu->is_active,
                'sections' => $menu->menuCategories->map(fn (MenuCategory $category): array => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'items' => $category->menuItems->map(
                        fn (MenuItem $item): array => $this->presentItem($item, $currency),
                    )->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * One dish and the extras it can be ordered with.
     *
     * @return array<string, mixed>
     */
    private function presentItem(MenuItem $item, Currency $currency): array
    {
        return [
            'id' => $item->getKey(),
            'name' => $item->name,
            'price' => $item->formattedPrice($currency),
            'foodType' => $item->food_type->value,
            'isAvailable' => $item->is_available,
            'additions' => $item->additions->map(fn (MenuItemAddition $addition): array => [
                'id' => $addition->getKey(),
                'name' => $addition->name,
                'price' => $addition->isFree() ? null : $addition->formattedPrice($currency),
                'isAvailable' => $addition->is_available,
            ])->values()->all(),
        ];
    }
}
