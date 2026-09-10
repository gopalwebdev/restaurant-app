---
paths:
  - 'app/Filament/Admin/Resources/Menus/**'
---

# Menus

## One menu page holds a menu's whole structure
A menu's edit page carries four relation managers, in reading order: `CategoriesRelationManager`, `SubCategoriesRelationManager`, `FeaturedItemsRelationManager` and `CombosRelationManager`. Between them they are the entire shape of a menu on one screen, all four dragged into order in place.

Categories used to be a resource of their own in the navigation, listing every category of every restaurant's menus and grouping them by menu to make sense of it. **That resource is gone** — `app/Filament/Admin/Resources/MenuCategories/` no longer exists, and `MenuCategoryForm` now lives under `Resources/Menus/Schemas/`. A category only means something inside a menu, so the menu's own page is where it is created, renamed, hidden and reordered. The category form has no "which menu" select any more: the page it is on is the menu.

Dishes stay a resource of their own. There are far more of them, and they are what a restaurant edits daily. Their page is a **filtered flat list**, not a tree: a Menu filter, a Category filter narrowed to it, and a column naming the branch. Grouping was tried and removed — see `.ai/rules/tables.md`.

Dishes are dragged into order **one category at a time**, because a dish's `position` is only ever read within its own category and Filament flattens a grouped table while reordering. The dishes page offers the drag only once its category filter names one category; see `.ai/rules/tables.md` for why that is the shape.

The two category tables are `Menu::categories()` and `Menu::subCategories()` — the rows of `menu_categories` without and with a `parent_id`. Both are plain `hasMany`, so both reorder with Filament's own drag and drop and nothing else. `FeaturedItemsRelationManager` is the odd one out: it hangs off a `HasManyThrough` and needs its own `reorderTable()`, for the reason in `.ai/rules/tables.md`.

All four lists on this page rearrange, and all four are pinned by a test that calls `reorderTable()` rather than asserting a button is visible.

## Featured dishes are a flag on the dish, reordered on the menu's own page
`menu_items.is_featured` plus `featured_position` are what a menu leads with; FeaturedItemsRelationManager on EditMenu is where they are dragged into order. It hangs off `Menu::menuItems()`, a HasManyThrough that reaches dishes through their sections because a dish carries its section and its restaurant, never its menu.

Nothing is created, deleted **or featured** there. The relation manager has no actions at all: it lists the featured dishes and lets them be dragged into order, which is the one thing the dish's own form cannot do. Whether a dish is featured is the `is_featured` toggle on that form — see `.ai/rules/actions-menus.md`. A featured dish is still sent inside its section on the guest's menu screen as well as in the featured rail.

`featured_position` is deliberately separate from `position`, which orders a dish inside its section. A dish answers both questions at once and the two orders are unrelated.

Featuring belongs to one menu, so a dish carried to a category on **another** menu is unfeatured on the way — by `MenuItem::booted()` for a single dish and by `MoveCategoryToMenu` for a whole branch. Without that, a dish would appear at the top of a menu nobody had chosen it for, at whatever `featured_position` it happened to hold.

## Combos hang off the menu, and are priced on their own
`menu_combos` sits beside the featured row rather than under a category: a combo is something a menu leads with, not something in a section, and "which category does a burger meal belong to" is a question with no answer worth having.

Its price is typed, never derived from `menu_combo_items`. The whole point of a combo is that it costs less than the sum of its parts, so a derived price would either be that sum or a discount rule nobody asked for — `MenuCombo::contentsPriceMinorUnits()` exists only to show the saving beside the price, never to set it. Repricing a dish therefore never silently reprices a combo.
