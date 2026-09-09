---
paths:
  - 'lang/**'
---

# Lang

## One file per app per language, keys mirrored, placeholders filled in the browser
lang/{en,ta}/guest.php and lang/{en,ta}/staff.php hold the chrome of the two phone apps — one file per app so a guest never downloads "Sold out" and staff never download the tile empty state. Nothing a restaurant wrote belongs here: menu, section, dish, addition and tile names are translated database columns (see .ai/rules/models.md).

Each file is sent to the browser whole as the `translations` Inertia prop and read with useTranslations()'s dotted path. Laravel's `:name` placeholders are therefore filled in JavaScript, not PHP — keep them in the string rather than writing a second string with the number baked in.

Every non-English file must mirror the English one's keys; a missing key falls back to English (merged in HandleTenantInertiaRequests::translationsFor()) and then to the path itself. tests/Feature/LocalizationTest.php asserts the key sets match and that each App\Enums\Locale case has a directory, so adding a language means adding a case, a directory, and a full file.
