<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role, as stored.
 *
 * App\Enums\Role declares the roles this application ships with, and the
 * RolesAndPermissionsSeeder writes them here. A super admin may add further
 * roles from the platform panel; those have no enum case and are the ones
 * this class calls custom.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Keep the roles the application declares intact.
     *
     * The panel never offers these moves, so reaching them means code has gone
     * around the resource: App\Enums\Role names are referenced from code and
     * the seeder owns their permissions, and either change would break that.
     */
    protected static function booted(): void
    {
        static::updating(static function (self $role): void {
            throw_if(
                $role->isDirty('name') && RoleEnum::tryFrom((string) $role->getOriginal('name')) instanceof RoleEnum,
                LogicException::class,
                'A built-in role may not be renamed.',
            );
        });

        static::deleting(static function (self $role): void {
            throw_if($role->isBuiltIn(), LogicException::class, 'A built-in role may not be deleted.');
        });
    }

    /**
     * Whether this role is one the application itself declares.
     *
     * A built-in role is referenced by name from code (Role::Admin->value),
     * and the seeder owns its permissions, so it may not be renamed, deleted,
     * or have its permissions edited from the panel.
     */
    public function isBuiltIn(): bool
    {
        return RoleEnum::tryFrom($this->name) instanceof RoleEnum;
    }

    /**
     * The enum case this role stores, if it is a built-in one.
     */
    public function toEnum(): ?RoleEnum
    {
        return RoleEnum::tryFrom($this->name);
    }

    /**
     * Limit the query to roles a restaurant may hand out.
     *
     * A role carrying any platform permission is withheld: restaurant panels
     * assign roles, and assigning one of these would let a restaurant admin
     * mint platform staff.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAssignableWithinRestaurant(Builder $query): void
    {
        $query->whereDoesntHave(
            'permissions',
            fn (Builder $permissions) => $permissions->whereIn('name', PermissionEnum::platformOnlyValues()),
        );
    }
}
