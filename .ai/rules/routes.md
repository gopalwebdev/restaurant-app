---
paths:
  - 'routes/**'
---

# Routes

## Routes follow OpenAPI resource naming
Paths name resources, never actions: plural lowercase kebab-case nouns, with the HTTP method carrying the verb. `GET /menu-items`, `POST /menu-items`, `PATCH /menu-items/{menuItem}` — never `/getMenuItems`, `/menu_items` or `/create-menu-item`. No trailing slash.

Nest only to express ownership, one level where possible: `/restaurants/{restaurant}/menu-items`. Path parameters are camelCase and match the route-model-binding variable.

API routes are versioned from the first one: `/api/v1/...`. Route names are dot-separated and mirror the path (`menu-items.index`), and links are always built with `route()` or the generated Wayfinder helper, never a hand-written string.

## Tenant route names say which app they belong to
Two apps share a restaurant's subdomain, so every route name on it carries its app's prefix: `guest.home`, `guest.menus.show`, `guest.tiles.document.show`, and `staff.home`, `staff.login`. The one exception is `preferences.language.update`, which both apps post to and neither owns.

`storefront` was the old name for `guest.home`, and `/` is now the tile home screen rather than the menu — a menu lives at `/menus/{menu}`. Because `{restaurant}` arrives in the **domain**, Laravel's scoped bindings do not cover `{menu}` or `{tile}`: each controller checks `$model->restaurant_id === $restaurant->getKey()` by hand, alongside the `is_active` check. Leaving that out is how one restaurant reads another's uploads off the same disk.
