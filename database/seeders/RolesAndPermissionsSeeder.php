<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the App\Enums\Permission and App\Enums\Role definitions into the
 * database, so a fresh install has something to work with.
 *
 * Idempotent, and deliberately one-directional: every permission the code
 * declares is created if missing, and a role is given its declared permissions
 * only on the run that creates it. Re-running never takes a permission back off
 * a role, because after the first run it is the product team, not this file,
 * that decides what a role grants.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, $guard);
        }

        // Roles resolve their permissions through the registrar cache, which is
        // now stale after the writes above.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions are written once, when the role is first created. After
        // that what a role grants belongs to the product team, who edit it from
        // the panel — so re-running this seeder must never revert their work.
        // The enum's list is a starting point, not a standing contract.
        foreach (RoleEnum::cases() as $role) {
            $existing = Role::query()->where('name', $role->value)->where('guard_name', $guard)->exists();

            if ($existing) {
                continue;
            }

            Role::findOrCreate($role->value, $guard)
                ->syncPermissions($role->permissionValues());
        }
    }
}
