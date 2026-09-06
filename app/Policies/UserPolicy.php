<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Who may manage the people attached to a restaurant.
 *
 * This is the restaurant's own roster: user.manage is held by the Admin and
 * Manager roles, and the panel only ever shows users of the restaurant whose
 * subdomain is being served, because Filament scopes the resource to the
 * current tenant.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    /**
     * Take a user off this restaurant's roster.
     *
     * Nobody may remove themselves: it is the one move that can leave a
     * restaurant with no way back into its own panel.
     */
    public function removeFromRestaurant(User $user, User $model): bool
    {
        return $user->can(Permission::UserManage->value) && ! $user->is($model);
    }
}
