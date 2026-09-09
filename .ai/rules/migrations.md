---
paths:
  - 'database/migrations/**'
---

# Migrations

## Strict schema, enums for fixed value sets, light normalization
Columns declare their real type and nullability — no catch-all strings, no nullable-by-default. Every relationship is a real foreign key with an explicit onDelete, and anything that must not repeat gets a unique or composite-unique index.

Any fixed set of values is a PHP backed enum in app/Enums/ (TitleCase cases) cast on the model, not a loose string column. The enum owns its own behaviour — see Role::permissions() and AdminPanel::path().

Normalize to roughly 3NF and stop: pull repeating groups into their own table with a pivot (restaurant_user), but do not shred simple value objects into tables for the sake of it.

## Money is stored as an integer in the minor unit
Never a float or a decimal string: every monetary column is an integer holding the smallest unit of its currency, so ₹249.50 is stored as 24950. Arithmetic stays exact and no rounding creeps in between the database and a payment provider. Convert to and from a display value at the edge, and name the column so the unit is unmistakable. The currency itself is on restaurant_settings.currency, cast to App\Enums\Currency.

## A phone number is two columns: calling code and national number
Never one free-text string. The calling code goes in its own column cast to App\Enums\CountryCallingCode (backing values carry the plus, '+91', which keeps them strings when used as array keys), and the national number goes in a column of its own holding digits only — ten of them for India. Size number columns to CountryCallingCode::longestMobileNumberLength(), so a country with longer numbers needs a migration as well as an enum case. Restaurant::dialablePhone() puts the two halves back together for display; nothing else should concatenate them by hand. See restaurants.phone_country_code/phone for the shape.

## A translated column is json, and its unique index is an expression
Any text a guest reads is a `json` column holding one key per App\Enums\Locale case, not a string — see `.ai/rules/models.md`. That makes a plain `$table->unique(['menu_id', 'name'])` useless: JSON documents only compare equal when every language in them does, so two menus both called "Dinner" slip through the moment their Tamil halves differ.

Put the constraint on the fallback language instead, with a raw statement, because Blueprint cannot express an expression index:

```php
DB::statement("CREATE UNIQUE INDEX menus_restaurant_id_name_en_unique ON menus (restaurant_id, (name ->> 'en'))");
```

Verified identical on the Postgres of development and the SQLite the test suite runs on. Two ordering rules that follow from it: convert values to JSON text **while the column is still text**, because Postgres will not cast `Starters` to json, and create the index **after** the type change, because changing a column's type rebuilds the table on SQLite and an index built beforehand would not survive it. `translate_menu_names_and_descriptions` does both in that order and its `down()` mirrors them.
