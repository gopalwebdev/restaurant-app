---
paths:
  - 'app/Actions/Menus/**'
---

# Actions Menus

## A category can move between a restaurant's menus, everything under it and all
`MoveCategoryToMenu` rewrites `menu_categories.menu_id`, and then unfeatures the dishes under it. Its sub-categories and dishes follow untouched because they carry `menu_category_id`, not `menu_id` — a category is the only thing that knows which menu it is on.

The unfeaturing is the one thing it does beyond the move: the featured row belongs to a menu, so dishes that have just left one must not appear at the top of the one they arrived on. `MoveItemToSection` applies the same rule to a single dish.

Both of its guards are backstops, thrown as LogicException: the target menu must belong to the same restaurant (the composite `(menu_id, tenant_id)` foreign key would refuse anyway, but as a 500), and the English name must be free on the target (uniqueness is per menu, so the expression index would reject the update after the form had passed). `CategoriesRelationManager`'s `moveToMenu` action states both as validation, which is what an admin actually sees — the action is what stops code going around the panel.

## A sub-category moves within one menu; a dish moves anywhere
Three actions, one shape: guards as `LogicException` backstops, with the panel restating them as validation.

- **`MoveSubCategoryToCategory`** is deliberately limited to the categories of **one menu**, and refuses a cross-menu target even though the composite foreign key would allow it. A sub-category has no menu of its own, so "move it to another menu" can only mean moving its category — offering both would be two ways to do one thing, and this one would silently split a category across two menus. It is a single `update()`; the dishes are carried by the `ON UPDATE CASCADE` on `menu_items`, and there is no order of two statements that would work instead (see `.ai/rules/models.md`).
- **`MoveItemToSection`** takes a category and an optional sub-category and writes both columns together, because a dish answers "where does this sit" with a pair. Passing only a category clears the sub-category, which is how a dish is lifted back up a level. A dish may move to any category in the restaurant, including one on another menu — that is a plain move, and it costs the dish its featured flag.

Uniqueness is only re-checked when the **category** changes: it is per category and built on the English name, so moving between two subdivisions of one category cannot introduce a clash.
