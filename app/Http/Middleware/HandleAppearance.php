<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Light or dark for the marketing site at the root domain.
 *
 * The same unencrypted cookie the two phone apps use, read through the same
 * enum, so there is one vocabulary for shading across the whole application.
 * The tenant apps do not go through here — HandleTenantInertiaRequests reads
 * the cookie itself, because it has a root template of its own to paint.
 */
class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie('appearance');

        View::share('appearance', Appearance::fromRequestValue(
            is_string($cookie) ? $cookie : null,
        )->value);

        return $next($request);
    }
}
