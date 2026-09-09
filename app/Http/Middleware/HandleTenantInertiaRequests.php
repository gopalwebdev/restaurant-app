<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Enums\Locale;
use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared behaviour for the two phone apps a restaurant serves.
 *
 * Both are resolved from the subdomain, both are branded by that restaurant's
 * settings, both let a visitor choose their own language and light or dark, and
 * both render through their own root template so their assets never mix. Each
 * subclass names its own template and its own translation file; everything else
 * is here.
 */
abstract class HandleTenantInertiaRequests extends Middleware
{
    /**
     * The lang/ file holding this app's chrome, without an extension.
     *
     * Guests and staff read different screens, so they are sent different
     * strings — a guest never downloads "Sold out" and staff never download
     * the tile empty state.
     */
    abstract protected function translationFile(): string;

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

        View::share('theme', $this->themeFor($restaurant, $request));

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
        $locale = Locale::fromRequestValue(app()->getLocale());

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
            'locale' => [
                'current' => $locale->value,
                // The one the toggle switches to. Worked out here rather than
                // in React so the button can name its destination without the
                // front end having to know the list of languages.
                'next' => $locale->next()->value,
                'available' => array_map(
                    static fn (Locale $available): array => [
                        'value' => $available->value,
                        'label' => $available->label(),
                        'shortLabel' => $available->shortLabel(),
                    ],
                    Locale::cases(),
                ),
            ],
            // Sent as a code and a scale rather than a formatted string,
            // because prices are formatted in the browser — see
            // resources/js/lib/money.ts.
            'currency' => $restaurant instanceof Restaurant ? [
                'code' => $restaurant->currency()->value,
                'minorUnitDigits' => $restaurant->currency()->minorUnitDigits(),
            ] : null,
            'translations' => $this->translationsFor($locale),
            // What the page was painted with, so the toggle starts in the right
            // state instead of guessing and correcting itself.
            'appearance' => $this->appearanceFor($request)->value,
        ];
    }

    /**
     * This app's chrome, in the language being served.
     *
     * Falls back key by key to English, so a Tamil file that is missing a
     * string shows the English one rather than the key itself.
     *
     * @return array<string, mixed>
     */
    protected function translationsFor(Locale $locale): array
    {
        $file = $this->translationFile();

        /** @var array<string, mixed> $fallback */
        $fallback = Lang::get($file, [], Locale::default()->value);

        if ($locale === Locale::default()) {
            return $fallback;
        }

        /** @var array<string, mixed> $translated */
        $translated = Lang::get($file, [], $locale->value);

        return array_replace_recursive($fallback, $translated);
    }

    /**
     * The theme handed to the root template.
     *
     * Just a name and light or dark. There is no brand colour and no per
     * restaurant default — see App\Enums\Appearance.
     *
     * @return array<string, string>
     */
    protected function themeFor(?Restaurant $restaurant, Request $request): array
    {
        return [
            'name' => $restaurant->name ?? config('app.name'),
            'appearance' => $this->appearanceFor($request)->value,
        ];
    }

    /**
     * Light or dark, as the phone reading this has been told.
     *
     * Read from the cookie rather than a prop because the answer has to be in
     * the first byte of HTML: React runs after the paint, so a prop would show
     * one shade and then visibly correct itself in front of the guest. See
     * resources/views/partials/theme.blade.php.
     */
    protected function appearanceFor(Request $request): Appearance
    {
        // A cookie is visitor-controlled and can come back as an array, so it
        // is checked rather than cast, and an unknown value falls back.
        $cookie = $request->cookie('appearance');

        return Appearance::fromRequestValue(is_string($cookie) ? $cookie : null);
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
