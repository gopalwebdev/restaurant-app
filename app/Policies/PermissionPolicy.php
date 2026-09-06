<?php

namespace App\Policies;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\User;

/**
 * Who may define the permissions roles are built from.
 *
 * permission.manage is a platform permission granted to no role, so only a
 * super admin passes. As with roles, the built-in guard lives on the resource
 * rather than here: Gate::before answers before a policy ever runs.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::PermissionManage->value);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(PermissionEnum::PermissionManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::PermissionManage->value);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(PermissionEnum::PermissionManage->value) && ! $permission->isBuiltIn();
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(PermissionEnum::PermissionManage->value) && ! $permission->isBuiltIn();
    }
}
