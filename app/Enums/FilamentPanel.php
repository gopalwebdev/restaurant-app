<?php

namespace App\Enums;

use Filament\Support\Icons\Heroicon;

/**
 * The Filament panels this application serves.
 *
 * Named for whose they are rather than for a role: a restaurant's panel is used
 * by its admins and its staff alike, and the platform panel by the product
 * team. The backing value is the panel's Filament id — what its route names are
 * built from — and path() is where it is served.
 *
 * Both panels live under /dashboard and are told apart by host: the platform's
 * on the root domain, a restaurant's on its own subdomain. People arrive
 * through /login on either host, which sends them to the panel's sign-in page
 * or, when they are already signed in, straight to /dashboard.
 */
enum FilamentPanel: string
{
    /** The product team's panel, on the root domain. */
    case Platform = 'platform';

    /** One restaurant's own panel, on its subdomain, for its admins and staff. */
    case Restaurant = 'restaurant';

    /**
     * The URL path this panel is served from.
     *
     * The same for both. That holds only because the platform panel is bound to
     * the root domain and registered first — the restaurant panel's sign-in
     * route answers on any host, since nobody has a tenant before signing in.
     * See bootstrap/providers.php.
     */
    public function path(): string
    {
        return 'dashboard';
    }

    /**
     * The mark this panel is branded with.
     *
     * A single storefront for the panel that runs one restaurant, and the
     * block of them for the panel that runs the platform.
     */
    public function icon(): Heroicon
    {
        return match ($this) {
            self::Platform => Heroicon::OutlinedBuildingOffice2,
            self::Restaurant => Heroicon::OutlinedBuildingStorefront,
        };
    }

    /**
     * The name this panel is branded with.
     */
    public function brandName(): string
    {
        return match ($this) {
            self::Platform => 'Restaurant Platform',
            self::Restaurant => (string) config('app.name'),
        };
    }

    /**
     * What the sign-in page says about where the visitor is signing in.
     */
    public function signInDescription(): string
    {
        return match ($this) {
            self::Platform => 'Sign in to manage every restaurant on the platform.',
            self::Restaurant => 'Sign in to manage your restaurant.',
        };
    }

    /**
     * The backing values of every panel.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $panel): string => $panel->value,
            self::cases(),
        );
    }
}
