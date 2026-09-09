<?php

namespace App\Actions\Users;

use App\Actions\Restaurants\EnsureRoleFitsWithinLimit;
use App\Enums\Role as RoleEnum;
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
    public function __construct(private readonly EnsureRoleFitsWithinLimit $ensureRoleFits) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function __invoke(User $user, array $roleNames): void
    {
        $roles = Role::query()->whereIn('name', $roleNames)->get();

        // A restaurant panel refuses to touch the roles of someone who staffs
        // more than one restaurant, because a role is held per account and
        // would change what they can do everywhere at once (see
        // .ai/rules/restaurants.md) — this panel is exactly where that call
        // is made, so it checks every restaurant the grant would apply to,
        // not just one.
        foreach ($user->restaurants()->get() as $restaurant) {
            foreach ($roles as $role) {
                $roleEnum = RoleEnum::tryFrom($role->name);

                if ($roleEnum instanceof RoleEnum) {
                    ($this->ensureRoleFits)($restaurant, $roleEnum, $user);
                }
            }
        }

        $user->syncRoles($roles);
    }
}
