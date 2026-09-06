---
paths:
  - app/Models/User.php
---

# Models

## Platform ownership is the is_super_admin column, not a role
`users.is_super_admin` is the single source of truth for platform staff. `User::isSuperAdmin()` reads it, `canAccessPanel()` gates the super-admin panel on it, and `AppServiceProvider::configureAuthorization()` uses `Gate::before` to grant a super admin every permission without holding any role.

There is deliberately no `Role::SuperAdmin`. Spatie roles describe what someone does inside one restaurant (Admin, Manager, Staff, Customer); platform ownership is global and orthogonal. Do not reintroduce a super-admin role — two sources of truth for this will drift.

The model declares `protected $attributes = ['is_super_admin' => false]` because the database default only lands on insert; without it an unsaved User throws MissingAttributeException under `Model::shouldBeStrict()`.

## The users resource puts a tenancy global scope on User
UserResource lives in the tenant panel, so Filament registers a tenancy global scope on User (and attaches anyone created during a tenant request to that restaurant). Any query that must see accounts platform-wide has to say so: ->withoutGlobalScope(Filament::getTenancyScopeName()), as AddUserToRestaurant does when finding an existing account by address. Sign-in is unaffected because Filament resolves the tenant from the authenticated user, so a visitor at the login page has none. The scope only exists once the panel has booted, which HTTP requests do via middleware and the enterRestaurantPanel() test helper does with Filament::bootCurrentPanel() — a test that only calls setCurrentPanel() proves nothing about tenant isolation.
