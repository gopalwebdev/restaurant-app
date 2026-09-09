---
paths:
  - 'app/Filament/Admin/Resources/Menus/**'
---

# Menus

## Featured dishes are a flag on the dish, reordered on the menu's own page
`menu_items.is_featured` plus `featured_position` are what a menu leads with; FeaturedItemsRelationManager on EditMenu is where they are dragged into order. It hangs off `Menu::menuItems()`, a HasManyThrough that reaches dishes through their sections because a dish carries its section and its restaurant, never its menu.

Nothing is created or deleted there — featuring adds a dish to the row or takes it out, and either way the dish stays on the menu under its own section. That is why the relation manager has a `feature` header action and an `unfeature` row action instead of create/delete, and why a featured dish is still sent inside its section on the guest's menu screen as well as in the featured rail.

`featured_position` is deliberately separate from `position`, which orders a dish inside its section. A dish answers both questions at once and the two orders are unrelated.
