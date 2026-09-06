<?php

namespace App\Enums;

/**
 * The roles a user may hold.
 *
 * Each role owns the definitive list of permissions granted to it, so the
 * seeder stays a thin projection of what is declared here.
 */
enum Role: string
{
    /** Platform owner. Manages every restaurant from the root domain. */
    case SuperAdmin = 'super-admin';

    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case Customer = 'customer';

    /**
     * The permissions granted to this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            // A restaurant admin owns everything inside their own tenant, but
            // may not manage the roster of restaurants on the platform.
            self::Admin => self::everyPermissionExcept(Permission::RestaurantManage),
            self::Manager => [
                Permission::MenuView,
                Permission::MenuManage,
                Permission::OrderViewAny,
                Permission::OrderManage,
                Permission::UserManage,
            ],
            self::Staff => [
                Permission::MenuView,
                Permission::OrderViewAny,
                Permission::OrderManage,
            ],
            self::Customer => [
                Permission::MenuView,
                Permission::OrderCreate,
                Permission::OrderViewOwn,
            ],
        };
    }

    /**
     * Every permission except the ones named.
     *
     * @return list<Permission>
     */
    private static function everyPermissionExcept(Permission ...$excluded): array
    {
        return array_values(array_filter(
            Permission::cases(),
            static fn (Permission $permission): bool => ! in_array($permission, $excluded, strict: true),
        ));
    }

    /**
     * The backing values of the permissions granted to this role.
     *
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return array_map(
            static fn (Permission $permission): string => $permission->value,
            $this->permissions(),
        );
    }

    /**
     * The backing values of every role.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
