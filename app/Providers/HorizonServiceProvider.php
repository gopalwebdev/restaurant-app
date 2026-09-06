<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * Queue contents span every restaurant on the platform, so the dashboard
     * belongs to platform staff rather than to any one restaurant's admin.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewHorizon',
            static fn (?User $user): bool => $user instanceof User && $user->isSuperAdmin(),
        );
    }
}
