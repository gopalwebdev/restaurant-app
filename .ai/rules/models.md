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

## Every level of the menu carries tenant_id, enforced by composite foreign keys
The menu is five levels — `menus` → `menu_categories` → `menu_sub_categories` → `menu_items` → `menu_item_additions` — plus `menu_combos` and `menu_combo_items` hanging off a menu, and every one of them carries `tenant_id` directly as well as reaching it through its parent. The home screen is two — `home_rows` → `home_tiles` — and does the same, as does a tile for the menu it opens.

That duplication is deliberate and safe: each table has a unique `(id, tenant_id)`, and its child has a **composite** foreign key on `(parent_id, tenant_id)` referencing the pair. Filing a section under another restaurant's menu, a dish under another restaurant's section, or an addition on another restaurant's dish is therefore a database error, not something a forgotten `where()` can let through. Every one of those is pinned by a test in `tests/Feature/Admin/MenuManagementTest.php`.

`menu_items` carries a **second** composite key that is not about tenancy: `(menu_sub_category_id, menu_category_id)` references `menu_sub_categories (id, menu_category_id)`, so a dish can only name a sub-category belonging to the very category it is filed under. A null sub-category means the constraint is not evaluated at all, which is how the majority of dishes — the ones filed straight under a category — pass it.

That key is `ON UPDATE CASCADE`, and that is load-bearing rather than decorative: it is what makes moving a sub-category between categories possible at all. A dish stores both halves, so rewriting only the sub-category's own `menu_category_id` would orphan every dish under it, and rewriting the dishes first would do the same in the other direction — there is no order of two statements that is legal at every step. `MoveSubCategoryToCategory` is therefore a single `update()`, and the dishes follow inside it. Do not "fix" that action by adding a second update.

Keep both columns in step when writing rows. The factories exist for exactly this — `MenuCategoryFactory::inMenu()`, `MenuSubCategoryFactory::inCategory()`, `MenuItemFactory::inCategory()` / `::inSubCategory()`, `MenuItemAdditionFactory::onItem()`, `MenuComboFactory::onMenu()`, `MenuComboItemFactory::pairing()`, `HomeTileFactory::inRow()` / `::openingMenu()` — and setting the halves independently trips the key. `MenuItemFactory::inSubCategory()` sets all three columns for that reason.

`MenuCategory`, `MenuSubCategory`, `MenuCombo`, `MenuComboItem`, `MenuItemAddition` and `HomeTile` all derive `tenant_id` from their parent in `booted()`, because Filament's tenancy stamps the model a *resource* is saving but not the rows a repeater or a relation manager writes alongside it. Categories joined that list when they stopped being a resource of their own and moved onto the menu's page.

A booted Filament panel stamps `tenant_id` on **every** model created during the request, so a factory that picks its own restaurant will now trip these keys. Create fixtures before `enterRestaurantPanel()`, or name the parent explicitly.

Never resolve an item's currency or tax rate through `$item->restaurant->settings`: that is a lazy load, which `Model::shouldBeStrict()` throws on outside production and which is an N+1 down a list of dishes. `App\Models\Concerns\IsPricedOnAMenu` holds `currency()`, `taxRate()`, `formattedPrice()` and `formattedStrikePrice()` once for both `MenuItem` and `MenuCombo`; every reader takes an optional override, and a list should pass one, because every row shares the restaurant's answer.

## Nothing on the menu is hard-deleted to take it off
`menu_items.availability` and `menu_combos.availability` are `App\Enums\ItemAvailability` — Available, OutOfStock, TemporarilyUnavailable — not a boolean. The boolean could only say *whether* a dish was off, so a kitchen that had run out of prawns and one that had stopped serving biryani looked identical, and both read to a guest as though the dish had been withdrawn.

`isOrderable()` is the single place "showing" and "orderable" are distinguished, and `ItemAvailability::orderableValues()` is what queries filter on, so a fourth case cannot leave a query behind. The reason never reaches a guest: `MenuController` leaves an unorderable dish out of the payload entirely rather than sending it greyed, because a phone menu should not be scrolled past things nobody can have.

`menu_item_additions` deliberately keeps a plain boolean `is_available`. An addition that has run out is simply not offered, so there is nothing for the extra cases to say there.

## Guest-facing text is a translated JSON column, unique on English
Menu, MenuCategory, MenuItem, MenuItemAddition, HomeRow and HomeTile store their guest-facing text with spatie/laravel-translatable: the column is `json` holding one key per App\Enums\Locale case, and the model declares `public array $translatable`. Use App\Models\Concerns\HasTranslatedNames, never Spatie's trait directly — it adds the one thing the package leaves open, which is that English (Locale::default()) is privileged.

English is required in the admin forms, is the fallback a guest gets when a translation is missing, and is what every unique index is built on. Those indexes are expression indexes created with a raw `DB::statement` (`... (menu_id, (name ->> 'en'))`) because Blueprint cannot express one; verified identical on Postgres and the SQLite the tests run on.

Consequences: a plain `unique` on the column would compare whole JSON documents and let duplicates through; `where('name', $x)` never matches, use `where(Model::fallbackLocalePath(), $x)` or `'name->en'`; `pluck('name->en')` comes back keyed by the path, so read models and use `$model->name`; and `orderBy`/`searchable` in a Filament table must go through TranslatedFields::sort()/search(). Spatie's toArray() returns one language, so admin forms fill with `$record->fillTranslationsInto($data, ...)`.

## A tile's action and its destination are paired in the model, not the schema
home_tiles has three nullable destination columns — menu_id, document_path and url — and App\Enums\HomeTileAction::targetColumn() is the single place that says which one an action uses. HomeTile::booted() clears the ones the action does not use and throws when the required one is blank; HomeTileForm states the same rule as `visible()`/`required()` validation.

This would be a CHECK constraint if Laravel's Blueprint could express one and SQLite could add one after CREATE TABLE. Neither is true, so do not go looking for a database guarantee here — and do not remove the model guard, which is the only thing stopping a tile that goes nowhere. Adding a third action means an enum case, a column, and a case in targetColumn(); nothing else.

## Timestamps are CarbonImmutable, and prices leave as integers
`AppServiceProvider` calls `Date::use(CarbonImmutable::class)`, so every `@property` for `created_at` / `updated_at` says `CarbonImmutable`. A docblock saying `Carbon` is wrong and will have someone reaching for `->addDay()` expecting it to mutate.

Money stays an integer all the way out of PHP. `MenuItem::formattedPrice()` exists for the Filament tables, which are server rendered; the guest and staff apps are sent `price_minor_units` and format it themselves — see `.ai/rules/js.md`. Do not add a `formattedX()` accessor for an Inertia payload.

## The tenant foreign key is tenant_id on every table, so relationships name it
Every foreign key pointing at `restaurants` is called `tenant_id` — users, menus, menu_categories, menu_items, menu_item_additions, home_rows, home_tiles, restaurant_settings and the restaurant_user pivot. One word for the tenant boundary whichever table carries it, matching Filament's own tenancy vocabulary.

The cost is that Laravel's convention would infer `restaurant_id` from `Restaurant::class`, so every relationship must name the key explicitly: `belongsTo(Restaurant::class, 'tenant_id')`, `hasMany(Menu::class, 'tenant_id')`, `belongsToMany(User::class, 'restaurant_user', 'tenant_id', 'user_id')`. A relationship added without it will silently query a column that does not exist.

Relationship *names* are unchanged and still read `restaurant()` / `restaurants()` — Filament's `$tenantOwnershipRelationshipName` points at those, not at the column, which is why the rename did not touch panel tenancy. The generated constraint and index names still say `restaurant_id` (Postgres and SQLite carry a constraint's definition through a column rename but not its name); nothing queries by those names, and dropping the `(id, tenant_id)` uniques to rename them would take the composite foreign keys with them.
