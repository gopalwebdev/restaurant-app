<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Who may define the roles restaurants assign from.
 *
 * role.manage is a platform permission granted to no role, so only a super
 * admin passes. Whether a *particular* role may be edited is a separate
 * question — a built-in role is owned by App\Enums\Role and the seeder — and
 * that guard lives on the resource, because Gate::before lets a super admin
 * past any policy check before it runs.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RoleManage->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RoleManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RoleManage->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::RoleManage->value) && ! $role->isBuiltIn();
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::RoleManage->value) && ! $role->isBuiltIn();
    }
}
