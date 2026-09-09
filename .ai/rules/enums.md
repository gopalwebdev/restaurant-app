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

## A GST slab is basis points, and availability is a reason
Two enums back the menu's money and its shelf state, and both are deliberately not the obvious type.

`App\Enums\TaxRate` is backed by **basis points** — 5% is `500`, not `5` and not `0.05`. That keeps the whole tax calculation in integers, exactly as money is stored in minor units, so nothing between the database and a payment provider ever sees a float; a percentage backing would make 12.5% unrepresentable the moment a slab changed. The cases are India's GST slabs (0, 5, 12, 18, 28), which is a fixed government set, not a number a restaurant picks. `taxOn()` runs the arithmetic both ways round, and getting that backwards is the classic bug: 5% *of* 105 is 5.25, but the tax *inside* 105 is 5.00. `halfBasisPoints()` is the CGST/SGST split an intra-state invoice prints — presentation, not a second amount.

`App\Enums\ItemAvailability` replaced a boolean because "off the menu" has a reason worth carrying, and because nothing here is ever hard-deleted to hide it. `isOrderable()` and `orderableValues()` are the only places the distinction is made, so a fourth case cannot leave a query behind.
