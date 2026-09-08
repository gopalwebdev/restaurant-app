<?php

namespace App\Actions\Users;

use App\Enums\AdminPanel;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Filament\Facades\Filament;

/**
 * Open an account from the product team panel.
 *
 * The tenant column and the restaurant roster are written together: the column
 * says which restaurant the account belongs to, and the roster is what actually
 * lets them into that restaurant's panel. Setting one without the other would
 * show a tenant on a row for someone who cannot open it.
 *
 * A null restaurant leaves the account belonging to the platform. That alone
 * grants nothing — is_super_admin is what does — so it is passed separately.
 */
class CreateUserAccount
{
    public function __construct(private readonly SetUserRoles $setRoles) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function __invoke(
        string $name,
        string $email,
        ?int $tenantId = null,
        bool $isSuperAdmin = false,
        array $roleNames = [],
    ): User {
        $restaurant = $tenantId === null
            ? null
            : Restaurant::query()->find($tenantId);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'tenant_id' => $restaurant?->getKey(),
            'is_super_admin' => $isSuperAdmin,
        ]);

        if ($restaurant instanceof Restaurant) {
            $user->restaurants()->syncWithoutDetaching([$restaurant->getKey()]);
        }

        ($this->setRoles)($user, $roleNames);

        $user->notify(new AccountCreatedNotification(
            $this->signInUrlFor($restaurant),
            $restaurant?->name,
        ));

        return $user->refresh();
    }

    /**
     * Where this account signs in: their restaurant's subdomain, or the
     * product team panel when they belong to no restaurant.
     */
    private function signInUrlFor(?Restaurant $restaurant): string
    {
        if ($restaurant instanceof Restaurant) {
            return $restaurant->adminSignInUrl();
        }

        return Filament::getPanel(AdminPanel::SuperAdmin->value)->getLoginUrl()
            ?? url(AdminPanel::SuperAdmin->path());
    }
}
