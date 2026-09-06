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
