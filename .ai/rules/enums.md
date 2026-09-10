---
paths:
  - 'app/Enums/**'
  - app/Enums/Role.php
---

# Enums

## Permission categories come from the name, not a column
`App\Enums\PermissionGroup` files every permission under a category, and the mapping lives in exactly one place: each case's `subjects()` list, naming the subject half of `subject.ability`. `forPermissionName()` reads it, and the permissions table filter has to ask the same question in SQL, so it reads it too — via `everySubject()` for the Other bucket, which is whatever no group claims.

This is why a permission added from the panel is categorised without anything being declared for it. Adding a permission with a new subject means adding that subject to a group, or it lands in Other.

`PermissionGroup::ProductTeam` and `Permission::productTeamOnlyValues()` must stay in step — one decides where a permission is shown, the other whether a restaurant may be offered a role holding it. A test in PermissionManagementTest pins them together.

## Four kinds of account; a role's name is binding, its permissions are not
There are exactly four kinds of account: the product team (`users.is_super_admin`, not a role) plus three Spatie roles — `admin` runs one restaurant, `staff` works in it, `guest` eats there. Do not add a fourth role without asking; `manager` and `customer` were deliberately removed and remapped (manager→admin, customer→guest).

What a role *grants* is not owned by the code. `App\Enums\Role::permissions()` is a starting point the seeder writes only on the run that first creates the role — re-running never reverts it, because after that the product team edits permissions from the panel. Only the **name** is binding, because code calls `hasRole('admin')`; the name is `disabled()` in RoleForm for built-in roles and `Role::booting()` throws on a rename or delete.

## Availability is a reason, not a boolean
`App\Enums\ItemAvailability` replaced a boolean on `menu_items` and `menu_combos` because "off the menu" has a reason worth carrying, and because nothing here is ever hard-deleted to hide it. `isOrderable()` and `orderableValues()` are the only places the distinction between "showing" and "orderable" is made, so a fourth case cannot leave a query behind.

## A tax rate is a number, not an enum — this was tried and reverted
GST rates are **not** a fixed value set, and modelling them as one was a mistake worth recording. `App\Enums\TaxRate` existed briefly with cases for the 0/5/12/18/28 slabs; India's GST 2.0 reform of 22 September 2025 collapsed those to 0/5/18 plus a 40% demerit rate, so the enum was wrong on the day it was written. Rates also vary by choice, not just by law — a standalone restaurant may elect 5% without input tax credit or 18% with it.

So `tax_rate_basis_points` is a plain nullable integer on `menu_items`, `menu_item_additions` and `menu_combos`, and a non-nullable one on `restaurant_settings`. A restaurant types the percentage its accountant gives it.

Basis points rather than a percentage float, though — 5% is `500`. That keeps every rate an exact integer, exactly as money is an exact integer in minor units, so nothing between the database and a payment provider ever sees a float. `App\Filament\Schemas\PricingFields` is the only place a typed percentage becomes basis points and back, so the rounding happens once; `RestaurantSetting::BASIS_POINTS_PER_WHOLE` is the unit and `::DEFAULT_TAX_RATE_BASIS_POINTS` the starting point.
