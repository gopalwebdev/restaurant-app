---
paths:
  - 'app/Filament/**/Tables/*.php'
---

# Tables

## Never group or sort a Filament table on a translated (JSON) column
Filament's table grouping (`->groups()`/`->defaultGroup()`) orders the query by the group's raw column or relationship attribute unless you override `orderQueryUsing()`. If that attribute is a translated column (json), Postgres throws "could not identify an ordering operator for type json" — SQLite tolerates it silently, so the test suite (SQLite) will not catch this; it only surfaces against Postgres dev/prod.

Group on the parent's integer foreign key instead (e.g. `menu_id`, not `menu.name`), and supply `getTitleFromRecordUsing()` for the header text and `orderQueryUsing()` ordering by the parent's own `position` column via a correlated subquery. See MenuCategoriesTable and MenuItemsTable for the pattern. This bit twice in production (menu categories and menu items both 500'd) before being fixed.
