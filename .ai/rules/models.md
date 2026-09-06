---
paths:
  - app/Models/User.php
---

# Models

## Platform ownership is the is_super_admin column, not a role
`users.is_super_admin` is the single source of truth for platform staff. `User::isSuperAdmin()` reads it, `canAccessPanel()` gates the super-admin panel on it, and `AppServiceProvider::configureAuthorization()` uses `Gate::before` to grant a super admin every permission without holding any role.

There is deliberately no `Role::SuperAdmin`. Spatie roles describe what someone does inside one restaurant (Admin, Manager, Staff, Customer); platform ownership is global and orthogonal. Do not reintroduce a super-admin role — two sources of truth for this will drift.

The model declares `protected $attributes = ['is_super_admin' => false]` because the database default only lands on insert; without it an unsaved User throws MissingAttributeException under `Model::shouldBeStrict()`.
