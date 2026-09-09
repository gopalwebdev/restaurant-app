<?php

namespace App\Actions\Staff;

use App\Enums\Permission;
use App\Models\Restaurant;
use App\Models\User;

/**
 * Finds the account at an address that may work this restaurant's floor.
 *
 * Two things have to hold, and both are checked here rather than at the call
 * site: the account is on this restaurant's roster, and it holds menu.view,
 * which is the permission the staff app is built on. A restaurant admin passes
 * as well — they are staff who can also do more.
 */
class AuthenticateStaffMember
{
    public function findEligible(Restaurant $restaurant, string $email): ?User
    {
        $user = User::query()->withEmail($email)->first();

        if (! $user instanceof User) {
            return null;
        }

        return $this->mayWorkHere($restaurant, $user) ? $user : null;
    }

    /**
     * Whether this account may open this restaurant's staff app.
     */
    public function mayWorkHere(Restaurant $restaurant, User $user): bool
    {
        // The product team are deliberately not waved through on the roster
        // check: they support restaurants from the panels, and an account with
        // no roster entry has no business taking orders on a floor.
        if (! $user->restaurants()->whereKey($restaurant)->exists()) {
            return false;
        }

        return $user->can(Permission::MenuView->value);
    }
}
