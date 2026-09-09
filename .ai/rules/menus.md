---
paths:
  - 'app/Filament/Admin/Resources/Menus/**'
---

# Menus

## One menu page holds a menu's whole structure
A menu's edit page carries four relation managers, in reading order: `CategoriesRelationManager`, `SubCategoriesRelationManager`, `FeaturedItemsRelationManager` and `CombosRelationManager`. Between them they are the entire shape of a menu on one screen, all four dragged into order in place.

Categories used to be a resource of their own in the navigation, listing every category of every restaurant's menus and grouping them by menu to make sense of it. **That resource is gone** — `app/Filament/Admin/Resources/MenuCategories/` no longer exists, and `MenuCategoryForm` now lives under `Resources/Menus/Schemas/`. A category only means something inside a menu, so the menu's own page is where it is created, renamed, hidden and reordered. The category form has no "which menu" select any more: the page it is on is the menu.

Dishes stay a resource of their own. There are far more of them, they are what a restaurant edits daily, and their page is a tree rather than a list — see `.ai/rules/tables.md`.

`SubCategoriesRelationManager` hangs off `Menu::menuSubCategories()`, a `HasManyThrough`, because a sub-category reaches its menu through its category and has no menu column. Two consequences: Filament saves a record created against a through-relationship **directly** rather than via the relation, which is exactly right here since the form names the category; and the through-join breaks Filament's own reordering, which is why that class overrides `reorderTable()` — see `.ai/rules/tables.md`.

## Featured dishes are a flag on the dish, reordered on the menu's own page
`menu_items.is_featured` plus `featured_position` are what a menu leads with; FeaturedItemsRelationManager on EditMenu is where they are dragged into order. It hangs off `Menu::menuItems()`, a HasManyThrough that reaches dishes through their sections because a dish carries its section and its restaurant, never its menu.

Nothing is created or deleted there — featuring adds a dish to the row or takes it out, and either way the dish stays on the menu under its own section. That is why the relation manager has a `feature` header action and an `unfeature` row action instead of create/delete, and why a featured dish is still sent inside its section on the guest's menu screen as well as in the featured rail.

`featured_position` is deliberately separate from `position`, which orders a dish inside its section. A dish answers both questions at once and the two orders are unrelated.

Featuring belongs to one menu, so a dish carried to a category on **another** menu is unfeatured on the way — by `MoveItemToSection` for a single dish and by `MoveCategoryToMenu` for a whole branch. Without that, a dish would appear at the top of a menu nobody had chosen it for, at whatever `featured_position` it happened to hold.

## Combos hang off the menu, and are priced on their own
`menu_combos` sits beside the featured row rather than under a category: a combo is something a menu leads with, not something in a section, and "which category does a burger meal belong to" is a question with no answer worth having.

Its price is typed, never derived from `menu_combo_items`. The whole point of a combo is that it costs less than the sum of its parts, so a derived price would either be that sum or a discount rule nobody asked for — `MenuCombo::contentsPriceMinorUnits()` exists only to show the saving beside the price, never to set it. Repricing a dish therefore never silently reprices a combo.
