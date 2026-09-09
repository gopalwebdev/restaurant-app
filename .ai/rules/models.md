---
paths:
  - app/Models/User.php
  - 'app/Models/**'
  - app/Models/MenuItem.php
  - app/Models/HomeTile.php
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

## Every level of the menu carries restaurant_id, enforced by composite foreign keys
The menu is four levels — `menus` → `menu_categories` → `menu_items` → `menu_item_additions` — and every one of them carries `restaurant_id` directly as well as reaching it through its parent. `home_tiles` does the same for the menu it opens.

That duplication is deliberate and safe: each table has a unique `(id, restaurant_id)`, and its child has a **composite** foreign key on `(parent_id, restaurant_id)` referencing the pair. Filing a section under another restaurant's menu, a dish under another restaurant's section, or an addition on another restaurant's dish is therefore a database error, not something a forgotten `where()` can let through. Every one of those is pinned by a test in `tests/Feature/Admin/MenuManagementTest.php`.

Keep both columns in step when writing rows. The factories exist for exactly this — `MenuCategoryFactory::inMenu()`, `MenuItemFactory::inCategory()`, `MenuItemAdditionFactory::onItem()`, `HomeTileFactory::openingMenu()` — and setting the two halves independently trips the key. `MenuItemAddition::booted()` derives `restaurant_id` from its dish, because Filament's tenancy stamps the model a resource is saving but not the rows a repeater writes alongside it.

A booted Filament panel stamps `restaurant_id` on **every** model created during the request, so a factory that picks its own restaurant will now trip these keys. Create fixtures before `enterRestaurantPanel()`, or name the parent explicitly.

Never resolve an item's currency through `$item->restaurant->settings`: that is a lazy load, which `Model::shouldBeStrict()` throws on outside production and which is an N+1 down a list of dishes. Use `MenuItem::currency()`, or better, pass the currency into `formattedPrice()` once for the whole list — every dish on a menu shares one.

## Guest-facing text is a translated JSON column, unique on English
Menu, MenuCategory, MenuItem, MenuItemAddition and HomeTile store their guest-facing text with spatie/laravel-translatable: the column is `json` holding one key per App\Enums\Locale case, and the model declares `public array $translatable`. Use App\Models\Concerns\HasTranslatedNames, never Spatie's trait directly — it adds the one thing the package leaves open, which is that English (Locale::default()) is privileged.

English is required in the admin forms, is the fallback a guest gets when a translation is missing, and is what every unique index is built on. Those indexes are expression indexes created with a raw `DB::statement` (`... (menu_id, (name ->> 'en'))`) because Blueprint cannot express one; verified identical on Postgres and the SQLite the tests run on.

Consequences: a plain `unique` on the column would compare whole JSON documents and let duplicates through; `where('name', $x)` never matches, use `where(Model::fallbackLocalePath(), $x)` or `'name->en'`; `pluck('name->en')` comes back keyed by the path, so read models and use `$model->name`; and `orderBy`/`searchable` in a Filament table must go through TranslatedFields::sort()/search(). Spatie's toArray() returns one language, so admin forms fill with `$record->fillTranslationsInto($data, ...)`.

## A tile's action and its destination are paired in the model, not the schema
home_tiles has two nullable destination columns — menu_id and document_path — and App\Enums\HomeTileAction::targetColumn() is the single place that says which one an action uses. HomeTile::booted() clears the ones the action does not use and throws when the required one is blank; HomeTileForm states the same rule as `visible()`/`required()` validation.

This would be a CHECK constraint if Laravel's Blueprint could express one and SQLite could add one after CREATE TABLE. Neither is true, so do not go looking for a database guarantee here — and do not remove the model guard, which is the only thing stopping a tile that goes nowhere. Adding a third action means an enum case, a column, and a case in targetColumn(); nothing else.
