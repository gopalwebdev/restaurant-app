<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a staff member sees once they are in.
 *
 * Orders are the point of this app and do not exist yet, so for now it shows
 * the floor what is on and what has sold out — which is the other thing they
 * are asked at a table all evening, and is real rather than a placeholder.
 */
class HomeController extends Controller
{
    public function __invoke(Restaurant $restaurant): Response
    {
        $currency = $restaurant->currency();

        // Read as categories-with-items rather than items-with-a-category:
        // one query shape shared with the guest menu, and nothing to reach
        // back through. Unlike the guest menu, hidden sections and sold-out
        // dishes are all here — staff are asked for them all evening.
        $sections = MenuCategory::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->with(['menuItems' => fn ($items) => $items->orderBy('position')->orderBy('name')])
            ->inMenuOrder()
            ->get();

        return Inertia::render('home', [
            'sections' => $sections->map(fn (MenuCategory $category): array => [
                'name' => $category->name,
                'items' => $category->menuItems->map(fn (MenuItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->name,
                    'price' => $item->formattedPrice($currency),
                    'foodType' => $item->food_type->value,
                    'isAvailable' => $item->is_available,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
