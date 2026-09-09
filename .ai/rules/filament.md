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
A role or permission whose name has an App\Enums case is built-in, because code refers to it by that name. What is withheld is narrow and deliberate:

- a built-in **role**: its permissions are fully editable, its name is not, and it may never be deleted
- a built-in **permission**: read-only, since its name is what `can()` checks
- any permission a role holds, or any role a user holds, may not be deleted until that link is undone

Those guards live in RoleResource/PermissionResource::canDelete() (and `disabled()` on the name field), NOT in the policy, because AppServiceProvider's Gate::before returns true for a super admin before any policy method runs — a policy check would be skipped for exactly the people who can reach these pages. Role and Permission models also throw from updating/deleting hooks as a backstop, which is why neither resource offers a bulk delete.

Both panels run `strictAuthorization()`, so a resource whose policy lacks the method being asked about is refused rather than waved through. tests/Feature/PanelAuthorizationTest.php walks every registered resource and page in both panels and asserts an account holding nothing is refused, so a page added later cannot ship open.

## Panels are for laptops and larger screens, not phones
Both panels are back-office tools — the product team's, and a restaurant admin's — used sitting down at a laptop or a bigger display. "Staff" in this codebase means the floor staff on phones, who are not in a panel at all. Design for that width and do not spend effort making a panel page work on a phone: no phone-first layouts, and no hiding columns below a breakpoint with visibleFrom()/hiddenFrom(), which only costs information when a laptop window is dragged narrow. A table may show every column it needs, and a form may assume the room to use columns().

This is enforced, not just intended: both panels render `resources/views/filament/desktop-only.blade.php` through the `BODY_START` render hook, which covers the panel with a "open this on a laptop" message below 1024px. It is CSS-only and inline, so it is correct on first paint and needs none of the utilities a panel does not ship. It is a door, not a second layout — building a phone layout for a panel is exactly what this rule rules out.

Guests and staff are the opposite — see .ai/rules/js.md, which is phone-only. Staff are deliberately not a panel: they are on phones, and this rule is the reason.

Panels are served Filament's own compiled CSS, which carries its fi- classes and no general Tailwind utilities, so any styling of your own needs inline styles or a panel theme rather than utility classes.

## Never bind roles or permissions with Filament's ->relationship()
A `CheckboxList::make('permissions')->relationship(...)` writes the pivot table directly, which bypasses Spatie's `syncRoles()` / `syncPermissions()` and so never calls `forgetCachedPermissions()`. The registrar then answers every `can()` check for the rest of the request from the set that was there before the save.

Use plain `->options()` instead, and hand the ids to an action on the page: SetRolePermissions (roles) or SetUserRoles (users). Both go through Spatie, which flushes the cache. Edit pages fill the field back in `mutateFormDataBeforeFill()`.

This is about **Spatie roles and permissions specifically**, because of that cache — it is not a ban on `->relationship()`. An ordinary `hasMany` has no cache behind it, and `MenuItemForm`'s additions repeater uses `->relationship()` on purpose so a dish and its extras are written in one save.

## Translated fields are two inputs side by side, never a locale switcher
Guest-facing text is stored one value per language (`.ai/rules/models.md`). Build the inputs with `App\Filament\Schemas\TranslatedFields::text()` / `::textarea()`, which emits one field per App\Enums\Locale case, requires only the fallback language, and attaches the uniqueness rule to that one — matching the database's expression index, so a save can never fail after passing validation.

Both languages are shown at once rather than behind tabs: with two languages and a handful of fields that is less machinery, and a name and its translation get edited together. Table columns must go through `TranslatedFields::sort()` / `::search()`, and every edit action needs `->mutateRecordDataUsing(fn (array $data, Model $record) => XForm::fillTranslations($data, $record))` — Spatie hands back one language, and a form editing all of them needs the whole document.

## The panel is worked in a language too, and its labels are methods
Both panels carry a language switcher in the top bar (`resources/views/filament/language-switcher.blade.php`, hung on `USER_MENU_BEFORE`) and both list `SetLocale` in their own middleware stack — a panel does not run the `web` group, so the middleware that reads the language cookie has to be named there as well.

The form posts to the host it was rendered on, because a cross-host post loses the session: the tenant panel uses `preferences.language.update` with the tenant's slug, and the product team's panel uses the root-domain `panel.language.update`. Both hit the same controller.

**Labels must be methods, not static properties.** A `protected static ?string $modelLabel = 'menu'` is evaluated when the class loads, before the request has chosen a language, so it can never translate. Use `getModelLabel()`, `getPluralModelLabel()` and `getNavigationGroup()` returning `__('panel....')`.

What is translated is deliberately bounded: the menu and storefront resources, in `lang/{en,ta}/panel.php`. **Roles, permissions, accounts and restaurants stay in English** — that is the product team's vocabulary and code refers to those names. Filament's own chrome falls back to English under Tamil, because the framework ships no `ta` locale.

## No theming in the panel
A restaurant chooses no colours and no light/dark default. The Settings page has contact and trading sections and nothing else; `App\Enums\Appearance` has two cases and lives on the phone. Do not add a theme section back without asking.
