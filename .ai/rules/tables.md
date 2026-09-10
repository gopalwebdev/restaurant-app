---
paths:
  - 'app/Filament/**/Tables/*.php'
  - 'app/Filament/Tables/**'
  - 'app/Filament/**/RelationManagers/*.php'
---

# Tables

## Never group or sort a Filament table on a translated (JSON) column
Filament's table grouping (`->groups()`/`->defaultGroup()`) orders the query by the group's raw column or relationship attribute unless you override `orderQueryUsing()`. If that attribute is a translated column (json), Postgres throws "could not identify an ordering operator for type json" — SQLite tolerates it silently, so the test suite (SQLite) will not catch this; it only surfaces against Postgres dev/prod.

Group on the parent's integer foreign key instead (e.g. `menu_id`, not `menu.name`), and supply `getTitleFromRecordUsing()` for the header text and `orderQueryUsing()` ordering by the parent's own `position` column via a correlated subquery. See SubCategoriesRelationManager and MenuItemsTable for the pattern. This bit twice in production (menu categories and menu items both 500'd) before being fixed.

## The dishes page does not group — it filters
Grouping was removed from MenuItemsTable, not fixed, and the reasons are worth keeping because a tree is the obvious thing to reach for.

Two things broke it. Filament turns grouping **off** while reordering (below), so the tree vanished exactly when it was most needed. And a group header only renders when its title differs from the previous row's — so two sections of the same name on different menus, or any ordering that did not keep a branch contiguous, fragmented into the same heading repeated down the page.

What replaced it: a **Menu** filter and a **Category** filter, the second narrowed to the first, and a category column showing the branch (`MenuCategory::path()`, "Biryani › Chicken") so a flat row still says where it sits. Ordering that grouping used to imply is now stated by the query — menu position, then the section a dish reads under, then a section's own dishes before its subdivisions', then `position`. It is `orderByRaw` because each rank is a correlated subquery over `menu_categories`, which appears twice: once as the dish's own category and once as that category's parent.

Every rank is `COALESCE`d rather than left null. Postgres sorts nulls last ascending and SQLite sorts them first, so a null would put the sections at opposite ends of the list on the two engines — and the suite runs on SQLite.


## Filament turns grouping off while reordering, so a grouped table cannot drag
`CanGroupRecords::getTableGrouping()` returns `null` when the table is reordering, and `CanSortRecords` sorts by the reorder column alone. There is no grouped drag mode to switch on: entering reorder mode always flattens the table.

That bit the dishes page while it still grouped: pressing "Rearrange" threw the tree away and produced one flat list of every dish on every menu, interleaved by `position` — and because a dish's position is only ever read *within its own category*, dropping one between two others rewrote a number that meant nothing there, so the row sprang back on the next load. It is half of why that page stopped grouping at all.

The fix is to narrow the table before offering the drag, not to fight the framework. `MenuItemsTable` passes a condition to `reorderable()` that is true only when the category filter names one category; below that, the trigger is simply not there. `isReorderable()` is `filled(column) && condition && authorized`, so a condition narrows *when* dragging is offered without touching *who* may do it — the `reorder()` policy check is the separate third term. It guards the write too: `reorderTable()` short-circuits on the same call, so a request that arrives without the filter set does nothing.

Nothing on the page explains this, deliberately: picking a category is the same filter an admin already uses to find a dish, and the button appearing when they do is the explanation. See `.ai/rules/filament.md`.

Any reorderable table that is also grouped needs this treatment, or its drag mode is a lie.

## Reordering a HasManyThrough table needs its own reorderTable()
Filament reorders by running one UPDATE over `$table->getQuery()`, keyed on the model's **unqualified** `id`. When the table is backed by a `HasManyThrough` that query carries a join, and both the `where in (id, ...)` and the `case when id = ...` it builds become "ambiguous column name: id" — on SQLite and Postgres alike. Dragging a row 500s.

`FeaturedItemsRelationManager` hits this, because it hangs off `Menu::menuItems()` — a HasManyThrough that reaches dishes through their categories. It overrides `reorderTable()` and issues the same update against `menu_items` alone, with the menu named as a plain subquery instead of a join. `makeTableReorderColumnExpression()` is `protected` on the trait, so the expression itself is reused rather than rewritten.

This shipped broken and untested for a while: dragging the featured row 500'd, and nothing caught it because the reorder tests only covered tables backed by a plain `hasMany`. **Any reorderable table on a through-relationship needs this override and a test that calls `reorderTable()` for real.** `SubCategoriesRelationManager` needed one too until both levels of category merged into one table and its relationship became a plain `hasMany`.

Keep the `isReorderable()` check first and unchanged in any such override: it is the `reorder()` policy method, and it is the only thing keeping the drag away from someone who may only read the menu.

## Hand-arranged lists drag, and the position field is never typed
Every ordered list in the panel — menus, categories, dishes, home screen rows, the tiles in a row, a menu's featured dishes — uses Filament's own `->reorderable('position')` drag and drop, with its trigger relabelled by `App\Filament\Tables\Reordering::trigger()`: "Rearrange" enters drag mode, "Done" leaves it.

Worth knowing: rows save as they are dropped, not when "Done" is pressed. That button confirms the admin has finished, it does not commit anything — Filament has no deferred-save reorder mode, and building one means overriding `reorderTable()`.

A short-lived pair of move-up/move-down buttons replaced the drag mode and was replaced right back: chevrons have to be clicked once per place moved, and dragging a category from the bottom of a long menu to the top is one gesture. Do not swap back to buttons without saying so.

The `position` TextInput is gone from every form, though: nobody arranges a menu by working out that starters should be 30 and desserts 40. The column still exists and is still what the guest app orders by — it is simply never typed in, and every table needs `->defaultSort('position')`.

`reorderTable()` short-circuits on `isReorderable()`, which is the `reorder()` policy method — so a test that only asserts the button is hidden proves nothing. Call `->call('reorderTable', [...])` and assert the positions did not move.
