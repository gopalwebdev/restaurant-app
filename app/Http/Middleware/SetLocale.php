<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language this request is answered in.
 *
 * A guest picks a language on their own phone, so the choice lives in a cookie
 * rather than on an account — guests do not have one. The panels read the same
 * cookie, because the language someone reads a screen in is a property of the
 * device in front of them rather than of the account.
 *
 * The cookie is unencrypted (see bootstrap/app.php) so the language toggle in
 * React and this middleware read the same value, exactly as the appearance
 * cookie already works. It is visitor-controlled and therefore never trusted:
 * anything that is not a known locale falls back to English.
 */
class SetLocale
{
    /**
     * The cookie both this middleware and the guest app read.
     */
    public const string COOKIE = 'locale';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->localeFor($request)->value);

        return $next($request);
    }

    /**
     * The language this visitor reads in.
     *
     * Falls back to the application's configured locale rather than to English
     * directly, so a deployment that changes its default is followed here.
     */
    private function localeFor(Request $request): Locale
    {
        // A cookie can come back as an array, so it is checked rather than cast.
        $cookie = $request->cookie(self::COOKIE);
        $chosen = is_string($cookie) ? Locale::tryFrom($cookie) : null;

        $configured = config('app.locale');

        return $chosen
            ?? (is_string($configured) ? Locale::tryFrom($configured) : null)
            ?? Locale::default();
    }
}
