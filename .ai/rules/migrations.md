---
paths:
  - 'database/migrations/**'
---

# Migrations

## Strict schema, enums for fixed value sets, light normalization
Columns declare their real type and nullability — no catch-all strings, no nullable-by-default. Every relationship is a real foreign key with an explicit onDelete, and anything that must not repeat gets a unique or composite-unique index.

Any fixed set of values is a PHP backed enum in app/Enums/ (TitleCase cases) cast on the model, not a loose string column. The enum owns its own behaviour — see Role::permissions() and AdminPanel::path().

Normalize to roughly 3NF and stop: pull repeating groups into their own table with a pivot (restaurant_user), but do not shred simple value objects into tables for the sake of it.
