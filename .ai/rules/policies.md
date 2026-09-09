---
paths:
  - 'app/Policies/**'
---

# Policies

## strictAuthorization means a missing policy method is a 500, not a default
Both panels run `->strictAuthorization()`, so Filament throws when a policy lacks the method it is asking for rather than quietly allowing access. That catches unauthorised pages, but it also means **adding a table feature can break a page until its policy catches up**.

The one that bites: `->reorderable('column')` makes Filament ask for `reorder()`, which is not one of the methods `make:policy` generates. `MenuPolicy`, `MenuCategoryPolicy`, `MenuItemPolicy`, `HomeRowPolicy` and `HomeTilePolicy` all have it — every one of those tables is reorderable, because the order is what the restaurant is arranging. Bulk actions likewise need `deleteAny()`.

When you add a resource or a table capability, write the policy method in the same change and cover the page with a test that actually renders it — a policy check that is never exercised proves nothing.
