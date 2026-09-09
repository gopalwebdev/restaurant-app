---
paths:
  - 'lang/**'
---

# Lang

## One file per app per language, keys mirrored, placeholders filled in the browser
lang/{en,ta}/guest.php and lang/{en,ta}/staff.php hold the chrome of the two phone apps — one file per app so a guest never downloads "Sold out" and staff never download the tile empty state. Nothing a restaurant wrote belongs here: menu, section, dish, addition and tile names are translated database columns (see .ai/rules/models.md).

Each file is sent to the browser whole as the `translations` Inertia prop and read with useTranslations()'s dotted path. Laravel's `:name` placeholders are therefore filled in JavaScript, not PHP — keep them in the string rather than writing a second string with the number baked in.

Every non-English file must mirror the English one's keys; a missing key falls back to English (merged in HandleTenantInertiaRequests::translationsFor()) and then to the path itself. tests/Feature/LocalizationTest.php asserts the key sets match and that each App\Enums\Locale case has a directory, so adding a language means adding a case, a directory, and a full file.

## panel.php is the admin panel, and only its menu surfaces
`lang/{en,ta}/panel.php` holds the labels of the Filament resources a restaurant works in daily — menus, sections, dishes, additions and home screen tiles — plus the navigation groups they sit under.

Roles, permissions, accounts and restaurants are deliberately **not** in here. They are the product team's vocabulary, code refers to those names, and translating them would make a `hasRole('admin')` check read as though it might not be English. Filament's own chrome also stays English under Tamil: the framework ships no `ta` locale, and a half-translated panel reads worse than a consistent one.

Labels reach Filament through `getModelLabel()` / `getNavigationGroup()` methods rather than static properties — see `.ai/rules/filament.md` for why a static property can never translate.
