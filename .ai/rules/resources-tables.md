---
paths:
  - 'app/Filament/Admin/Resources/**/Tables/*.php'
---

# Resources Tables

## One name column, in whichever language the panel is being worked in
A table shows one column per translated field, not one per language. `TextColumn::make('name')` already renders Spatie's accessor, which answers in the panel's current locale — switching the language in the top bar changes what the column reads. The `name_ta` / `title_ta` / `label_ta` companion columns were removed: they duplicated a column that already answers the question, and cost a column of width on every table.

Searching and sorting still have to name a language, because the column is a JSON document — keep `TranslatedFields::search()` / `::sort()`, both of which work on the English value the unique indexes are built on.

The panel calls a top-level `menu_categories` row a **category** and a nested one a **sub-category**, never a section. `lang/*/panel.php` is the only place that wording lives; the model, the tables and the routes all say category, parent or menu_category — there is no `sub_category` anywhere in the schema, because both levels are one table.

A price column carries its struck-through "was" price as the column's `description()` rather than taking a column of its own, which would be empty for every row that is not on offer — most of them. The same trick carries a dish's description under its name.
