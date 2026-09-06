---
paths:
  - 'app/Filament/**'
---

# Filament

## Each panel owns its own Filament namespace and path
Two panels, kept apart on purpose:
- super-admin — root domain, /super-admin, classes under app/Filament/SuperAdmin/
- admin — restaurant subdomain, /admin, classes under app/Filament/Admin/

App\Enums\AdminPanel is the single source of truth for both the Filament panel id and its path; the providers and User::canAccessPanel() all read it. Never hardcode 'admin'/'super-admin' or a panel path anywhere else.

Never put a page, resource or widget where both panels discover it. Shared behaviour goes in an abstract base under app/Filament/Auth/ (see OtpLogin) and each panel registers its own thin subclass.

## Built-in roles and permissions are guarded on the resource, not the policy
A role or permission whose name has an App\Enums case is built-in: code refers to it by name and the seeder owns its permissions, so the panel offers view only. That guard lives in RoleResource/PermissionResource::canEdit()/canDelete(), NOT in the policy, because AppServiceProvider's Gate::before returns true for a super admin before any policy method runs — a policy check would be skipped for exactly the people who can reach these pages. Role and Permission models also throw from updating/deleting hooks as a backstop, which is why neither resource offers a bulk delete.

## Panels are for laptops and larger screens, not phones
Both panels are staff tools, used sitting down at a laptop or a bigger display. Design for that width and do not spend effort making a panel page work on a phone: no phone-first layouts, and no hiding columns below a breakpoint with visibleFrom()/hiddenFrom(), which only costs information when a laptop window is dragged narrow. A table may show every column it needs, and a form may assume the room to use columns().

The customer-facing side is the opposite — see the storefront rule in .ai/rules/js.md, which is phone-only.

Panels are served Filament's own compiled CSS, which carries its fi- classes and no general Tailwind utilities, so any styling of your own needs inline styles or a panel theme rather than utility classes.
