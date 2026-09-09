<?php

namespace App\Http\Middleware;

use App\Actions\Staff\AuthenticateStaffMember;
use App\Models\Restaurant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed-in staff member inside the restaurant they work at.
 *
 * Being signed in is not enough on its own: sessions are per domain but an
 * account is platform-wide, so without this someone who works at one
 * restaurant could walk into another's staff app by editing the subdomain.
 * The sign-in controller checks the same thing on the way in; this is what
 * keeps it true on every request afterwards.
 */
class EnsureStaffMemberWorksHere
{
    public function __construct(private readonly AuthenticateStaffMember $staff) {}

    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $request->route('restaurant');
        $user = $request->user();

        abort_unless($restaurant instanceof Restaurant, 404);
        abort_unless($user instanceof User, 403);
        abort_unless($this->staff->mayWorkHere($restaurant, $user), 403);

        return $next($request);
    }
}
