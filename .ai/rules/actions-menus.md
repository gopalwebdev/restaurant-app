---
paths:
  - 'app/Actions/Menus/**'
---

# Actions Menus

## A category can move between a restaurant's menus, dishes and all
`MoveCategoryToMenu` rewrites `menu_categories.menu_id` and nothing else. The dishes follow untouched because they carry `menu_category_id`, not `menu_id` — a category is the only thing that knows which menu it is on.

Both of its guards are backstops, thrown as LogicException: the target menu must belong to the same restaurant (the composite `(menu_id, tenant_id)` foreign key would refuse anyway, but as a 500), and the English name must be free on the target (uniqueness is per menu, so the expression index would reject the update after the form had passed). MenuCategoriesTable's `moveToMenu` action states both as validation, which is what an admin actually sees — the action is what stops code going around the panel.
