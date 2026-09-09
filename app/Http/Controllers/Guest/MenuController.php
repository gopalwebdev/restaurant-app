<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The menu a guest reads at the table, resolved from the subdomain.
 *
 * Only what is actually orderable is sent: a hidden section and a sold-out dish
 * are both absent rather than greyed out, because a guest reading a menu on a
 * phone should not be scrolling past things they cannot have.
 */
class MenuController extends Controller
{
    public function __invoke(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->is_active, 404);

        $currency = $restaurant->currency();

        $sections = MenuCategory::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->active()
            ->with(['menuItems' => fn ($items) => $items->where('is_available', true)->orderBy('position')->orderBy('name')])
            ->inMenuOrder()
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->menuItems->isNotEmpty());

        return Inertia::render('menu', [
            'sections' => $sections->map(fn (MenuCategory $category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'items' => $category->menuItems->map(fn (MenuItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->formattedPrice($currency),
                    'foodType' => $item->food_type->value,
                ])->values()->all(),
            ])->values()->all(),
            'acceptingOrders' => $restaurant->isAcceptingOrders(),
        ]);
    }
}
