<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission the application recognises.
 *
 * These values are persisted to the `permissions` table by the
 * RolesAndPermissionsSeeder, so treat them as a stable contract:
 * renaming a case requires a data migration.
 */
enum Permission: string
{
    case MenuView = 'menu.view';
    case MenuManage = 'menu.manage';
    case OrderCreate = 'order.create';
    case OrderViewOwn = 'order.view-own';
    case OrderViewAny = 'order.view-any';
    case OrderManage = 'order.manage';
    case UserManage = 'user.manage';

    /**
     * The backing values of every permission.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
