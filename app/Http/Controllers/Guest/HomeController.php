<?php

namespace App\Http\Controllers\Guest;

use App\Enums\HomeTileAction;
use App\Http\Controllers\Controller;
use App\Models\HomeTile;
use App\Models\Menu;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screen a guest lands on after scanning the QR code at their table.
 *
 * What is on it is the restaurant's arrangement, not ours: it chooses the
 * tiles, their pictures, their order and what each one opens. This reads that
 * arrangement and hands it over; the guest app draws whatever it is given.
 */
class HomeController extends Controller
{
    public function __invoke(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->is_active, 404);

        $tiles = HomeTile::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->active()
            // A menu tile pointing at a hidden menu would open an empty screen,
            // so the menu comes along and the ones that lead nowhere are
            // dropped below rather than shown and then apologised for.
            ->with('menu')
            ->inDisplayOrder()
            ->get()
            ->filter(fn (HomeTile $tile): bool => $this->leadsSomewhere($tile));

        return Inertia::render('home', [
            'tiles' => $tiles->map(fn (HomeTile $tile): array => [
                'id' => $tile->getKey(),
                'label' => $tile->label,
                'shape' => $tile->shape->value,
                'aspectRatio' => $tile->shape->aspectRatio(),
                'imageUrl' => $tile->hasImage()
                    ? route('guest.tiles.image.show', ['restaurant' => $restaurant->slug, 'tile' => $tile->getKey()])
                    : null,
                'href' => $this->destinationOf($tile, $restaurant),
            ])->values()->all(),
        ]);
    }

    /**
     * Whether tapping this tile would actually arrive anywhere.
     *
     * A menu tile whose menu has been hidden is the case that matters: the
     * menu row still exists, so the foreign key is satisfied and nothing is
     * broken, but a guest tapping it would be shown a menu the restaurant has
     * deliberately taken down.
     */
    private function leadsSomewhere(HomeTile $tile): bool
    {
        if ($tile->action !== HomeTileAction::Menu) {
            return true;
        }

        // menu_id is nullable because a PDF tile has none, so a menu tile leads
        // somewhere only when its menu is both there and showing.
        $menu = $tile->menu;

        return $menu instanceof Menu && $menu->is_active;
    }

    /**
     * Where this tile takes the guest.
     *
     * Built here rather than on the model because every URL on a tenant domain
     * needs the restaurant's slug, and reaching for it through the tile would
     * be a lazy load per row.
     */
    private function destinationOf(HomeTile $tile, Restaurant $restaurant): string
    {
        return match ($tile->action) {
            HomeTileAction::Menu => route('guest.menus.show', [
                'restaurant' => $restaurant->slug,
                'menu' => $tile->menu_id,
            ]),
            HomeTileAction::Pdf => route('guest.tiles.show', [
                'restaurant' => $restaurant->slug,
                'tile' => $tile->getKey(),
            ]),
        };
    }
}
