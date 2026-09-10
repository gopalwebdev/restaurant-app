<?php

namespace App\Enums;

use Filament\Support\Icons\Heroicon;

/**
 * The Filament panels this application serves.
 *
 * The backing value is the panel's Filament id — what its route names are built
 * from — and path() is where it is served. Both panels are served at /admin and
 * told apart by host: the product team's on the root domain, a restaurant's on
 * its own subdomain. Keeping both here stops the id, the route and the access
 * check drifting apart.
 */
enum AdminPanel: string
{
    /** The product team panel, on the root domain at /admin. */
    case SuperAdmin = 'super-admin';

    /** One restaurant's own panel, on its subdomain at /admin. */
    case Admin = 'admin';

    /**
     * The URL path this panel is served from.
     *
     * The same for both. That holds only because the product team panel is bound
     * to the root domain and registered first — the restaurant panel's sign-in
     * route answers on any host, since nobody has a tenant before signing in.
     * See bootstrap/providers.php.
     */
    public function path(): string
    {
        return 'admin';
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
            self::SuperAdmin => Heroicon::OutlinedBuildingOffice2,
            self::Admin => Heroicon::OutlinedBuildingStorefront,
        };
    }

    /**
     * The name this panel is branded with.
     */
    public function brandName(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Restaurant Platform',
            self::Admin => (string) config('app.name'),
        };
    }

    /**
     * What the sign-in page says about where the visitor is signing in.
     */
    public function signInDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Sign in to manage every restaurant on the platform.',
            self::Admin => 'Sign in to manage your restaurant.',
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
