<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LogicException;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A permission, as stored.
 *
 * App\Enums\Permission declares every permission the code actually checks;
 * the RolesAndPermissionsSeeder writes them here. A permission added from the
 * panel is inert until something in the application checks for it, which is
 * why the form says so.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * Keep the permissions the application declares intact.
     *
     * These names are what the code checks with $user->can(), so a rename or a
     * delete from anywhere but a migration silently revokes access.
     */
    protected static function booted(): void
    {
        static::updating(static function (self $permission): void {
            throw_if(
                $permission->isDirty('name') && PermissionEnum::tryFrom((string) $permission->getOriginal('name')) instanceof PermissionEnum,
                LogicException::class,
                'A built-in permission may not be renamed.',
            );
        });

        static::deleting(static function (self $permission): void {
            throw_if($permission->isBuiltIn(), LogicException::class, 'A built-in permission may not be deleted.');
        });
    }

    /**
     * Whether this permission is one the application itself declares.
     *
     * A built-in permission is checked by name from code, so it may not be
     * renamed or deleted from the panel.
     */
    public function isBuiltIn(): bool
    {
        return PermissionEnum::tryFrom($this->name) instanceof PermissionEnum;
    }

    /**
     * A human readable name, from the enum where there is one.
     */
    public function label(): string
    {
        return PermissionEnum::tryFrom($this->name)?->label() ?? $this->name;
    }
}
