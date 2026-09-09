---
paths:
  - 'app/**'
---

# App

## Accounts have no password: sign-in is an emailed one-time code
There is no `password` column, no password_reset_tokens table, and no Fortify. The only way into either Filament panel is App\Filament\Auth\Login, a two-step page that issues a code and then checks it.

User::getAuthPassword() deliberately returns '' rather than being removed. Laravel's AuthenticateSession middleware (in both panel middleware stacks) reads it on every request and falls through when it is empty; without the override the model would throw under Model::shouldBeStrict().

Do not reintroduce a password field, a "forgot password" flow, or passkeys without saying so explicitly.

## Thin controllers, behaviour in invokable action classes
HTTP and Livewire/Filament classes stay thin: they validate, call one thing, and return a response. Every unit of behaviour is a single-purpose invokable class under app/Actions/<Area>/ (see app/Actions/Otp/), resolved with app(...) and called as $action($args).

Follow SOLID: one reason to change per class, depend on the abstraction, extend rather than branch on a type. Prefer a model query scope over repeating a where clause in a controller or page (see User::scopeWithEmail).

Laravel's own idiom wins over cleverness: named routes, Form Requests, Eloquent relationships, artisan make: for new files, and `vendor/bin/pint` before finishing.

## India is the only market for now
Defaults are Indian: CountryCallingCode has one case (+91) and mobile numbers validate as ten digits, and addresses take a pincode. This is a "for now", not a permanent assumption, so keep this shape multi-country: values that vary by country belong in an enum with a case per country rather than hardcoded in a form or a rule. Add the country to the enum rather than branching on it at the call site.

Currency and timezone are a harder line than that, by product decision, not just a default: `App\Enums\Currency` has exactly one case (`IndianRupee`) and `restaurant_settings` carries no timezone column at all — the application timezone comes from `APP_TIMEZONE`/`config('app.timezone')` alone (`.ai/rules/config.md`), never a per-restaurant choice. Re-adding either a currency picker or a per-restaurant timezone needs a product decision first, not just an enum case — this project has explicitly decided against them "for now," which is a stronger statement than the multi-country shape above.

## A restaurant caps its own admins and staff
`restaurants.max_admins` and `restaurants.max_staff` (default from `config('restaurants.php')`, editable per restaurant by a super admin on RestaurantForm) bound how many accounts may hold the Admin or Staff role on that restaurant's roster at once — one admin and five staff out of the box. `Restaurant::roleLimit()` and `Restaurant::roleHolderCount()` answer "how many, and how many allowed"; `App\Actions\Restaurants\EnsureRoleFitsWithinLimit` is the single place every role grant is checked against it, called from `SetRestaurantUserRoles` (tenant panel) and `SetUserRoles` (product team panel) — never bypass either action to write a role directly.

Lowering a limit below the restaurant's current roster is refused at the form field (`RestaurantForm::notBelowCurrentHolders()`), naming how many to remove first, rather than silently locking the extra accounts out of a role they still hold.

## Say "product team", not "platform staff"
The people who run the whole product are the **product team** — that is the vocabulary in class names, method names, comments and UI copy: `isProductTeamOnly()`, `productTeamOnlyValues()`, `belongsToProductTeam()`, `enterProductTeamPanel()`, and "Product team" wherever a null tenant is rendered.

"Platform" is still correct for the *software*, and is deliberately kept: "accounts are platform-wide", "every restaurant on the platform", and the super-admin panel's brand name "Restaurant Platform". The distinction is people versus product — do not rename those back.

## Four surfaces: two Filament panels, two React apps
Settled architecture, one surface per audience:

1. **Product team** — Filament, root domain `/super-admin`. Restaurants, roles, permissions, accounts.
2. **Restaurant admin** — Filament, tenant subdomain `/admin`. Menu, settings, reports, receipt printing.
3. **Staff** — React + Inertia, phone-first, installed as a PWA. Order taking and status.
4. **Guest** — React + Inertia, phone-first, no install; arrives by QR and lands on a home screen the restaurant arranges out of rows of tiles (`home_rows` → `home_tiles`), walking from there into a menu, a PDF, or off to a link.

Staff are React rather than a third Filament panel because they are on phones: `.ai/rules/filament.md` reserves panels for laptop-and-larger, and order-taking is the highest-frequency screen in the product, where a Livewire round-trip per tap is the wrong trade. Neither React surface works offline — there is no offline requirement, and Inertia needs the server too.

Keeping guests and staff as the only Inertia surfaces is also what keeps their bundles free of Filament assets. Do not import Filament into either, and do not add a Filament panel for a phone audience.

## The menu is five levels, and a guest lands on tiles rather than on it
`menus` → `menu_categories` → `menu_sub_categories` → `menu_items` → `menu_item_additions`. A restaurant that serves one card all day simply keeps one menu; one that serves a different card at lunch has two.

The sub-category level is **optional and shallow on purpose**. A dish is always filed under a category and *may also* sit in one of that category's subdivisions, so a restaurant that never subdivides anything never sees the level at all — `menu_items` keeps a required `menu_category_id` beside a nullable `menu_sub_category_id`. There is deliberately no sub-sub-category: a self-referencing tree brings ordering and cycle problems for a depth no menu has asked for.

Alongside the sections, a menu carries `menu_combos` — bundles sold at one price, each listing existing dishes in `menu_combo_items` with a quantity. A combo hangs off the **menu** rather than a category, because it is something the menu leads with rather than something in a section, and its price is its own: a combo exists precisely because it costs less than the sum of its parts, so nothing derives one from the other.

Additions are a flat list of extras per dish (name plus a price delta, and zero is a real price — "no onions" costs nothing and is still worth listing). Grouped choices with rules — "pick exactly one size", "up to three toppings" — and per-dish variants are a real thing a menu eventually needs and belong in a layer **above** `menu_item_additions`, not inside it. Variants were considered and deliberately left out for now; sizes are modelled as additions until then.

What a guest sees first is `home_rows`, each holding its own `home_tiles`. The **row** owns the layout — `App\Enums\HomeRowLayout` is `Banner` (full-width rectangles), `Carousel` (a swipeable rail of pictures) or `Links` (small circles for Instagram, WhatsApp, a phone number) — and every tile in it is drawn that way, which is why there is no shape column on a tile. The **tile** owns its destination: a menu, an uploaded PDF, or an external link. That is why `/` is the home screen and a menu lives at `/menus/{menu}`.

"Row" and not "section": the panel already calls `menu_categories` sections, and one word for two different things is how a reader ends up on the wrong page.

A menu opens with two rows above its sections: the dishes the restaurant leads with (`menu_items.is_featured`, ordered by `featured_position`) and its combos. A featured dish still appears under its own section further down, so a guest scrolling finds it where they expect it.

Featuring belongs to **one menu**, so a dish that leaves a menu stops being featured — `MoveItemToSection` and `MoveCategoryToMenu` both clear the flag when the move crosses menus, rather than letting a dish appear at the top of a menu nobody chose it for.

Prices carry an optional `strike_price_minor_units` — the higher "was" price shown struck through — which is null on almost every row, because null is how a dish says it is not on offer and a zero would be a price of nothing. It is refused unless it is strictly above what is charged.

Money is not the only tax-relevant thing here: `menu_items` and `menu_item_additions` each carry a nullable GST slab (`tax_rate_basis_points`, cast to `App\Enums\TaxRate`) that falls back to `restaurant_settings.tax_rate_basis_points`, so a restaurant sets 5% once and only genuinely different lines — a sealed bottle taxed as goods rather than as restaurant service — override it. See `.ai/rules/config.md` and the settings page for the global rate, `prices_include_tax`, and the two optional charges.

## Two languages, English default, and everyone picks their own
`App\Enums\Locale` has one case per language the app is available in — English and Tamil for now — and is the single source of truth: the cookie middleware validates against it, the toggle is built from it, and every translated column stores one key per case. English is the default, the fallback, and the only language an admin form requires.

Like the India assumption above, this is a "for now": add a language by adding a case and a `lang/<value>` directory, not by branching on a locale at a call site. The one thing that does not scale for free is the expression unique indexes, which are built on English — see `.ai/rules/migrations.md`.

All four surfaces switch language, not just the phone apps: the guest and staff apps through their toggle, and both Filament panels through a switcher in the top bar. Only what a guest reads is translated, though — menu content in translated columns, and the panel's menu-related labels in `lang/*/panel.php`. Roles and permissions stay English; see `.ai/rules/lang.md`.
