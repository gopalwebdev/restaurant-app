---
paths:
  - 'app/Filament/**/Tables/*.php'
  - 'app/Filament/Tables/**'
---

# Tables

## Never group or sort a Filament table on a translated (JSON) column
Filament's table grouping (`->groups()`/`->defaultGroup()`) orders the query by the group's raw column or relationship attribute unless you override `orderQueryUsing()`. If that attribute is a translated column (json), Postgres throws "could not identify an ordering operator for type json" — SQLite tolerates it silently, so the test suite (SQLite) will not catch this; it only surfaces against Postgres dev/prod.

Group on the parent's integer foreign key instead (e.g. `menu_id`, not `menu.name`), and supply `getTitleFromRecordUsing()` for the header text and `orderQueryUsing()` ordering by the parent's own `position` column via a correlated subquery. See MenuCategoriesTable and MenuItemsTable for the pattern. This bit twice in production (menu categories and menu items both 500'd) before being fixed.

## Hand-arranged lists drag, and the position field is never typed
Every ordered list in the panel — menus, categories, dishes, home screen rows, the tiles in a row, a menu's featured dishes — uses Filament's own `->reorderable('position')` drag and drop, with its trigger relabelled by `App\Filament\Tables\Reordering::trigger()`: "Rearrange" enters drag mode, "Done" leaves it.

Worth knowing: rows save as they are dropped, not when "Done" is pressed. That button confirms the admin has finished, it does not commit anything — Filament has no deferred-save reorder mode, and building one means overriding `reorderTable()`.

A short-lived pair of move-up/move-down buttons replaced the drag mode and was replaced right back: chevrons have to be clicked once per place moved, and dragging a category from the bottom of a long menu to the top is one gesture. Do not swap back to buttons without saying so.

The `position` TextInput is gone from every form, though: nobody arranges a menu by working out that starters should be 30 and desserts 40. The column still exists and is still what the guest app orders by — it is simply never typed in, and every table needs `->defaultSort('position')`.

`reorderTable()` short-circuits on `isReorderable()`, which is the `reorder()` policy method — so a test that only asserts the button is hidden proves nothing. Call `->call('reorderTable', [...])` and assert the positions did not move.
