<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;

/**
 * Set the roles an account holds, from the product team panel.
 *
 * Unlike SetRestaurantUserRoles this withholds nothing: a super admin is
 * exactly who decides that a role carrying a product team permission may be handed
 * out, and it is the only place that decision can be made.
 *
 * Roles go through Spatie's syncRoles() rather than the pivot so the permission
 * registrar's cache is flushed with them. A bare pivot sync would leave every
 * can() check for the rest of the request answering from the old set.
 */
class SetUserRoles
{
    /**
     * @param  list<string>  $roleNames
     */
    public function __invoke(User $user, array $roleNames): void
    {
        $roles = Role::query()->whereIn('name', $roleNames)->get();

        $user->syncRoles($roles);
    }
}
