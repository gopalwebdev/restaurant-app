<?php

namespace App\Enums;

/**
 * The Filament panels this application serves.
 *
 * The backing value is the panel's Filament id, and it doubles as the path the
 * panel is served from. Keeping both here stops the id, the route and the
 * access check drifting apart.
 */
enum AdminPanel: string
{
    /** The platform panel, on the root domain at /super-admin. */
    case SuperAdmin = 'super-admin';

    /** One restaurant's own panel, on its subdomain at /admin. */
    case Admin = 'admin';

    /**
     * The URL path this panel is served from.
     */
    public function path(): string
    {
        return $this->value;
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
