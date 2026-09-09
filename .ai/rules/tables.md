---
paths:
  - 'app/Filament/**/Tables/*.php'
  - 'app/Filament/Tables/**'
---

# Tables

## Never group or sort a Filament table on a translated (JSON) column
Filament's table grouping (`->groups()`/`->defaultGroup()`) orders the query by the group's raw column or relationship attribute unless you override `orderQueryUsing()`. If that attribute is a translated column (json), Postgres throws "could not identify an ordering operator for type json" — SQLite tolerates it silently, so the test suite (SQLite) will not catch this; it only surfaces against Postgres dev/prod.

Group on the parent's integer foreign key instead (e.g. `menu_id`, not `menu.name`), and supply `getTitleFromRecordUsing()` for the header text and `orderQueryUsing()` ordering by the parent's own `position` column via a correlated subquery. See MenuCategoriesTable and MenuItemsTable for the pattern. This bit twice in production (menu categories and menu items both 500'd) before being fixed.

## Hand-arranged lists use two arrow buttons, never a position field
Every ordered list in the panel — menus, sections, dishes, home screen rows, the tiles in a row, a menu's featured dishes — is arranged with the pair of icon buttons from `App\Filament\Tables\OrderActions::make()`, placed first in `recordActions()`. Filament's `->reorderable()` drag mode is gone, and so is the `position` TextInput that used to sit in every form: nobody arranges a menu by working out that starters should be 30 and desserts 40.

`OrderActions::make()` takes a closure returning the list a record is arranged *within*, and that scope matters — the sections of one menu, the tiles of one row. Handing it the whole table would let a tap on one menu shuffle another; there is a test for exactly that.

`App\Actions\Ordering\MoveRecordInList` does the swap and normalises the list's positions first, because seeds and imports leave duplicates behind and a swap between two records both sitting at 0 moves nothing.

The `position` column still exists and is still what the guest app orders by — it is simply never typed in. A table still needs `->defaultSort('position')`.
