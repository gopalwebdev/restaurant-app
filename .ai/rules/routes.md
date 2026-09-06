---
paths:
  - 'routes/**'
---

# Routes

## Routes follow OpenAPI resource naming
Paths name resources, never actions: plural lowercase kebab-case nouns, with the HTTP method carrying the verb. `GET /menu-items`, `POST /menu-items`, `PATCH /menu-items/{menuItem}` — never `/getMenuItems`, `/menu_items` or `/create-menu-item`. No trailing slash.

Nest only to express ownership, one level where possible: `/restaurants/{restaurant}/menu-items`. Path parameters are camelCase and match the route-model-binding variable.

API routes are versioned from the first one: `/api/v1/...`. Route names are dot-separated and mirror the path (`menu-items.index`), and links are always built with `route()` or the generated Wayfinder helper, never a hand-written string.
