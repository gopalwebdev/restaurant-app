<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The roles a user may hold.
 *
 * Each role owns the definitive list of permissions granted to it, so the
 * seeder stays a thin projection of what is declared here.
 */
enum Role: string
{
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
            self::Admin => Permission::cases(),
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
