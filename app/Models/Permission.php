<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Enums\PermissionGroup;
use App\Models\Concerns\ReadsLoadedCounts;
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

    use ReadsLoadedCounts;

    /**
     * Keep the permissions the application declares intact, and keep one a role
     * holds from disappearing out from under it.
     *
     * Registered in booting() rather than booted() for the same reason as
     * App\Models\Role: Spatie's own `deleting` listener, registered when its
     * traits boot in between, cuts the very links these guards ask about.
     *
     * These names are what the code checks with $user->can(), so a rename or a
     * delete from anywhere but a migration silently revokes access.
     */
    protected static function booting(): void
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

            // Deleting one out from under a role silently narrows what that
            // role grants, and nothing says so afterwards. Take it off every
            // role first, which is a decision per role.
            //
            // This asks the database rather than any loaded count: isInUse()
            // may answer from what a page loaded, which is right for deciding
            // what to show and wrong for deciding what to destroy.
            throw_if($permission->roles()->exists(), LogicException::class, 'A permission held by a role may not be deleted.');
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
     * Whether any role holds this permission.
     *
     * A permission in use may not be deleted: the roles holding it would each
     * quietly lose an ability, and a role is what the application checks.
     */
    public function isInUse(): bool
    {
        return $this->holderCount() > 0;
    }

    /**
     * How many roles hold this permission.
     *
     * Prefers the count the page already loaded, so a table asks once for every
     * row rather than once per row. The deleting hook asks the database
     * directly instead, because a stale count must never authorise a delete.
     */
    private function holderCount(): int
    {
        return $this->loadedCount('roles_count')
            ?? ($this->relationLoaded('roles') ? $this->roles->count() : $this->roles()->count());
    }

    /**
     * Why this permission may not be deleted, or null when it may be.
     *
     * The resource shows this and the deleting hook throws it, so the panel and
     * the model never disagree about the reason.
     */
    public function undeletableReason(): ?string
    {
        return match (true) {
            $this->isBuiltIn() => 'This permission is declared in code, so it may not be deleted.',
            $this->isInUse() => 'This permission is held by a role. Take it off every role before deleting it.',
            default => null,
        };
    }

    /**
     * The category this permission is shown under.
     *
     * PermissionGroup reads the subject half of the name, so a permission added
     * from the panel is grouped without anything being declared for it.
     */
    public function group(): PermissionGroup
    {
        return PermissionGroup::forPermissionName($this->name);
    }

    /**
     * A human readable name, from the enum where there is one.
     */
    public function label(): string
    {
        return PermissionEnum::tryFrom($this->name)?->label() ?? $this->name;
    }
}
