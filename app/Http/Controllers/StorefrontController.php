<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Show the public storefront for the restaurant named by the subdomain.
     */
    public function __invoke(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->is_active, 404);

        return Inertia::render('storefront', [
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
            ],
        ]);
    }
}
