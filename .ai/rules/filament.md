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

## Panels must work on a phone as well as a laptop
Every panel page is used on mobile, laptop and larger screens, so build for all three and check the narrow one. In practice: keep form sections on Filament's responsive grid (columns() collapses to one column on small screens) rather than fixing widths; give tables few enough always-visible columns to read on a phone and mark the rest toggleable(isToggledHiddenByDefault: true); never put several full-size actions in one row, since labels wrap badly at narrow widths — one primary action plus links beneath. Panels are served Filament's own compiled CSS, which has no general Tailwind utilities, so responsive tweaks of your own need inline styles with media queries or a panel theme, not utility classes.
