---
paths:
  - app/Models/User.php
  - 'app/Models/**'
  - app/Models/MenuItem.php
---

# Models

## Product team ownership is the is_super_admin column, not a role
`users.is_super_admin` is the single source of truth for the product team. `User::isSuperAdmin()` reads it, `canAccessPanel()` gates the super-admin panel on it, and `AppServiceProvider::configureAuthorization()` uses `Gate::before` to grant a super admin every permission without holding any role.

There is deliberately no `Role::SuperAdmin`. Spatie roles describe what someone does inside one restaurant (Admin, Manager, Staff, Customer); product team ownership is global and orthogonal. Do not reintroduce a super-admin role — two sources of truth for this will drift.

The model declares `protected $attributes = ['is_super_admin' => false]` because the database default only lands on insert; without it an unsaved User throws MissingAttributeException under `Model::shouldBeStrict()`.

## The users resource puts a tenancy global scope on User
UserResource lives in the tenant panel, so Filament registers a tenancy global scope on User (and attaches anyone created during a tenant request to that restaurant). Any query that must see accounts platform-wide has to say so: ->withoutGlobalScope(Filament::getTenancyScopeName()), as AddUserToRestaurant does when finding an existing account by address. Sign-in is unaffected because Filament resolves the tenant from the authenticated user, so a visitor at the login page has none. The scope only exists once the panel has booted, which HTTP requests do via middleware and the enterRestaurantPanel() test helper does with Filament::bootCurrentPanel() — a test that only calls setCurrentPanel() proves nothing about tenant isolation.

## Guards on Role and Permission go in booting(), never booted()
Spatie's HasPermissions trait registers a `deleting` listener that detaches a Role's users and permissions, and Eloquent boots traits between `booting()` and `booted()`. A guard registered in `booted()` is therefore asked its question *after* the links it inspects have already been cut, so `$role->users()->exists()` is always false there and the guard silently never fires.

Role::booting() and Permission::booting() hold the rename/delete guards for this reason. If you add another model event that inspects a Spatie relationship, register it in `booting()` too, and cover it with a test that calls `$model->delete()` directly — asserting `isInUse()` alone passes even when the guard is dead.

## tenant_id is where an account belongs; is_super_admin is what it may do
`users.tenant_id` names the restaurant an account belongs to and is what the product team panel lists it under — null renders as "Product team". It grants nothing. `is_super_admin` remains the only source of product team ownership, because an ordinary account that has not been put on a roster yet also has a null tenant, and deriving powers from that would hand the platform to every half-created user.

The restaurant_user pivot still exists alongside it: tenant_id is the one restaurant they belong to, the pivot is every restaurant they staff. Write both together (CreateUserAccount, EditUser::syncRoster, UserFactory::ofRestaurant) — a tenant nobody is rostered at names a panel the account cannot open.

## Read withCount values for display, query fresh before destroying
`Role::undeletableReason()` and `Permission::isInUse()` prefer a `withCount()` value already on the model (via the `ReadsLoadedCounts` trait) and fall back to a query. A list page loads those counts once for the whole page, so asking the model again per row cost 57 extra queries on the roles table before this — the page is now flat at 6 queries whatever the row count, pinned by a test in RoleManagementTest.

The `deleting` hooks deliberately do **not** use those helpers: they call `users()->exists()` / `permissions()->exists()` / `roles()->exists()` directly. A count loaded when a page rendered is right for deciding what to show and wrong for deciding what to destroy.

## A menu item's restaurant is enforced by a composite foreign key
`menu_items` carries `restaurant_id` directly as well as reaching it through `menu_category_id`. That duplication is deliberate and safe: `menu_categories` has a unique `(id, restaurant_id)`, and `menu_items` has a **composite** foreign key on `(menu_category_id, restaurant_id)` referencing it. Storing a dish under another restaurant's section is therefore a database error, not something a forgotten `where()` can let through.

Keep both columns in step when writing rows — `MenuItemFactory::inCategory()` exists for exactly this, and setting the two independently trips the key.

Never resolve an item's currency through `$item->restaurant->settings`: that is a lazy load, which `Model::shouldBeStrict()` throws on outside production and which is an N+1 down a list of dishes. Use `MenuItem::currency()`, or better, pass the currency into `formattedPrice()` once for the whole list — every dish on a menu shares one.
