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

## The dishes tree groups on a composite key, built by hand
MenuItemsTable's default group is the branch a dish sits on — a category, or one of that category's sub-categories — so a heading reads "Biryani › Chicken" and the dishes filed straight under Biryani get a group of their own above them.

Neither column can answer that alone: grouping by `menu_sub_category_id` would sweep every un-subdivided dish in the restaurant into one null group, and grouping by `menu_category_id` would flatten the subdivisions back out. So every piece Filament needs is supplied explicitly — `getKeyFromRecordUsing()` builds `"{category}:{sub}"`, `getTitleFromRecordUsing()` builds the heading, `scopeQueryByKeyUsing()` parses the key back into a `where` plus a **null check** for the empty half (`where(x, null)` matches nothing), and `orderQueryUsing()` orders by category position then sub-category position. The name passed to `Group::make()` is only an identifier there.

The sub-category rank is coalesced to `-1` rather than left null: Postgres sorts nulls last ascending and SQLite sorts them first, so a null would put a category's own dishes at opposite ends of its branch on the two engines — and the suite, which runs on SQLite, would never see it.

## Reordering a HasManyThrough table needs its own reorderTable()
Filament reorders by running one UPDATE over `$table->getQuery()`, keyed on the model's **unqualified** `id`. When the table is backed by a `HasManyThrough` that query carries a join, and both the `where in (id, ...)` and the `case when id = ...` it builds become "ambiguous column name: id" — on SQLite and Postgres alike. Dragging a row 500s.

SubCategoriesRelationManager therefore overrides `reorderTable()` and issues the same update against `menu_sub_categories` alone, with the menu named as a plain subquery instead of a join. `makeTableReorderColumnExpression()` is `protected` on the trait, so the expression itself is reused rather than rewritten.

Keep the `isReorderable()` check first and unchanged in any such override: it is the `reorder()` policy method, and it is the only thing keeping the drag away from someone who may only read the menu.

## Hand-arranged lists drag, and the position field is never typed
Every ordered list in the panel — menus, categories, dishes, home screen rows, the tiles in a row, a menu's featured dishes — uses Filament's own `->reorderable('position')` drag and drop, with its trigger relabelled by `App\Filament\Tables\Reordering::trigger()`: "Rearrange" enters drag mode, "Done" leaves it.

Worth knowing: rows save as they are dropped, not when "Done" is pressed. That button confirms the admin has finished, it does not commit anything — Filament has no deferred-save reorder mode, and building one means overriding `reorderTable()`.

A short-lived pair of move-up/move-down buttons replaced the drag mode and was replaced right back: chevrons have to be clicked once per place moved, and dragging a category from the bottom of a long menu to the top is one gesture. Do not swap back to buttons without saying so.

The `position` TextInput is gone from every form, though: nobody arranges a menu by working out that starters should be 30 and desserts 40. The column still exists and is still what the guest app orders by — it is simply never typed in, and every table needs `->defaultSort('position')`.

`reorderTable()` short-circuits on `isReorderable()`, which is the `reorder()` policy method — so a test that only asserts the button is hidden proves nothing. Call `->call('reorderTable', [...])` and assert the positions did not move.
