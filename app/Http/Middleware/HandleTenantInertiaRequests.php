<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared behaviour for the two phone apps a restaurant serves.
 *
 * Both are resolved from the subdomain, both are branded by that restaurant's
 * settings, and both render through their own root template so their assets
 * never mix. Each subclass names its own template; everything else is here.
 */
abstract class HandleTenantInertiaRequests extends Middleware
{
    /**
     * Put the restaurant's theme in front of the root template.
     *
     * Shared with the view rather than sent as an Inertia prop, because it has
     * to be in the HTML of the very first response: React runs after the paint,
     * so a prop would show the default colour and then correct itself in front
     * of the guest.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $this->restaurant($request);

        View::share('theme', $this->themeFor($restaurant));

        // The root templates build tenant URLs — a manifest, a service worker,
        // a scope — and every one of those routes carries {restaurant} in its
        // domain, so the slug has to travel with them.
        View::share('tenantSlug', $restaurant?->slug);

        return parent::handle($request, $next);
    }

    /**
     * The props shared with every page of a tenant app.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $restaurant = $this->restaurant($request);

        return [
            ...parent::share($request),
            'restaurant' => $restaurant instanceof Restaurant ? [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
            ] : null,
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
        ];
    }

    /**
     * The theme handed to the root template.
     *
     * @return array<string, string>
     */
    protected function themeFor(?Restaurant $restaurant): array
    {
        $settings = $restaurant?->settings;

        return [
            'name' => $restaurant->name ?? config('app.name'),
            'primary_color' => $settings->theme_primary_color ?? '#E11D48',
            'appearance' => $settings->theme_appearance->value ?? 'system',
        ];
    }

    /**
     * The restaurant this request is being served for.
     */
    protected function restaurant(Request $request): ?Restaurant
    {
        $restaurant = $request->route('restaurant');

        return $restaurant instanceof Restaurant ? $restaurant : null;
    }
}
