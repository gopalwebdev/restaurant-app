<?php

namespace App\Enums;

use Illuminate\Support\Str;

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

    /** Change one restaurant's own configuration. */
    case SettingsManage = 'settings.manage';

    /** Platform-level: create, suspend, and delete restaurants. */
    case RestaurantManage = 'restaurant.manage';

    /** Platform-level: define the roles every restaurant assigns from. */
    case RoleManage = 'role.manage';

    /** Platform-level: define the permissions those roles are built from. */
    case PermissionManage = 'permission.manage';

    /**
     * Whether this permission belongs to platform staff alone.
     *
     * A platform permission is never granted to a restaurant role, and a role
     * holding one is never offered inside a restaurant panel. Together those
     * two rules are what stop a restaurant admin handing out platform access.
     */
    public function isPlatformOnly(): bool
    {
        return match ($this) {
            self::RestaurantManage, self::RoleManage, self::PermissionManage => true,
            default => false,
        };
    }

    /**
     * A human readable name for this permission.
     */
    public function label(): string
    {
        [$subject, $ability] = explode('.', $this->value);

        return Str::of($ability)->replace('-', ' ')->headline()
            ->append(' ', Str::of($subject)->headline()->toString())
            ->toString();
    }

    /**
     * Every permission reserved for platform staff.
     *
     * @return list<self>
     */
    public static function platformOnly(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $permission): bool => $permission->isPlatformOnly(),
        ));
    }

    /**
     * The backing values of every permission reserved for platform staff.
     *
     * @return list<string>
     */
    public static function platformOnlyValues(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::platformOnly(),
        );
    }

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
