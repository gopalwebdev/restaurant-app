<?php

namespace App\Http\Middleware;

/**
 * The staff app, served under /staff on a restaurant's subdomain.
 *
 * Its own root template, which is also where the PWA manifest and the service
 * worker registration live — the two things that make this app installable and
 * the guest app deliberately not.
 */
class HandleStaffAppRequests extends HandleTenantInertiaRequests
{
    /**
     * @var string
     */
    protected $rootView = 'staff';
}
