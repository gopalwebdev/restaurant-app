<?php

namespace App\Http\Middleware;

/**
 * The guest app, served at the root of a restaurant's subdomain.
 *
 * Its own root template, so a guest downloads the guest entry and nothing else.
 */
class HandleGuestAppRequests extends HandleTenantInertiaRequests
{
    /**
     * @var string
     */
    protected $rootView = 'guest';
}
