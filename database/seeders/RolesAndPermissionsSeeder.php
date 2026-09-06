<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Projects the App\Enums\Role and App\Enums\Permission definitions into the
 * database.
 *
 * This seeder is idempotent, so it is safe to run on every deploy to keep the
 * stored roles in step with the code.
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

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, $guard)
                ->syncPermissions($role->permissionValues());
        }
    }
}
