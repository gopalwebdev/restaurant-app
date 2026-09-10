---
paths:
  - 'app/Providers/Filament/*.php'
  - app/Providers/Filament/RestaurantPanelProvider.php
---

# Providers Filament

## Both panels run with databaseTransactions() enabled
Filament's `hasDatabaseTransactions()` defaults to false, so a CreateRecord/EditRecord page runs `handleRecordCreation()`/`handleRecordUpdate()` with no transaction by default. A ValidationException thrown partway through a multi-step write (e.g. EnsureRoleFitsWithinLimit, thrown after the user row and roster attachment are already written but before roles sync) used to leave the earlier writes committed — a "refused" account that still existed on the roster with no role.

Both RestaurantPanelProvider and PlatformPanelProvider now call ->databaseTransactions(), so any exception thrown during a save rolls back the whole thing. Do not remove this without re-checking every action class invoked from a Create/Edit page's handleRecordCreation/handleRecordUpdate for multi-step writes that assume atomicity.

## The restaurant panel brands itself with the signed-in tenant, and has no tenant menu
RestaurantPanelProvider::brandName() resolves per request: the signed-in restaurant's own name once Filament::getTenant() is known, and FilamentPanel::Restaurant->brandName() (the generic panel name, from config('app.name')) before that — the sign-in page has no tenant yet. Both ->brandName() and the ->brandLogo() view (resources/views/filament/brand.blade.php, via its optional $name param) read from the same private brandName() helper.

->tenantMenu(false) is also set: a restaurant admin has nowhere to switch to (the panel is scoped to one restaurant), and a super admin supporting one opens it from the "Open dashboard" row action on the platform panel's Restaurants table instead (RestaurantsTable.php), which links to Restaurant::signInUrl(). Sessions are per-domain (.ai/rules/middleware.md), so this was never a same-session switch even when the tenant menu was on — removing it loses no real capability.

## PlatformPanelProvider registers before RestaurantPanelProvider
Both panels are served under `/dashboard`, and the order in `bootstrap/providers.php` decides which one the root domain's `/dashboard/login` belongs to — see `.ai/rules/filament.md`. Both providers also run `->spa(hasPrefetching: true)`.
