<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;

/**
 * Set the permissions a role grants.
 *
 * Every role's permissions are editable, built-in ones included: what a role
 * grants is the product team's to decide and change, and only its *name* is
 * binding, because code refers to a role by name.
 *
 * This goes through Spatie's syncPermissions() rather than the pivot, and that
 * is the whole point of the class. Writing the pivot directly — which is what a
 * Filament `->relationship()` checkbox list does — leaves the permission
 * registrar's cache holding the old set, so every can() check for the rest of
 * the request answers from before the change. syncPermissions() flushes it.
 */
class SetRolePermissions
{
    /**
     * @param  list<int|string>  $permissionIds
     */
    public function __invoke(Role $role, array $permissionIds): void
    {
        $permissions = Permission::query()->whereKey($permissionIds)->get();

        $role->syncPermissions($permissions);
    }
}
