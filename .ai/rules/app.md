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
Defaults are Indian: CountryCallingCode has one case (+91) and mobile numbers validate as ten digits, restaurant settings default to Asia/Kolkata and INR, and addresses take a pincode. This is a "for now", not a permanent assumption, so keep the shape multi-country: values that vary by country belong in an enum with a case per country rather than hardcoded in a form or a rule. Add the country to the enum rather than branching on it at the call site.

## Say "product team", not "platform staff"
The people who run the whole product are the **product team** — that is the vocabulary in class names, method names, comments and UI copy: `isProductTeamOnly()`, `productTeamOnlyValues()`, `belongsToProductTeam()`, `enterProductTeamPanel()`, and "Product team" wherever a null tenant is rendered.

"Platform" is still correct for the *software*, and is deliberately kept: "accounts are platform-wide", "every restaurant on the platform", and the super-admin panel's brand name "Restaurant Platform". The distinction is people versus product — do not rename those back.

## Four surfaces: two Filament panels, two React apps
Settled architecture, one surface per audience:

1. **Product team** — Filament, root domain `/super-admin`. Restaurants, roles, permissions, accounts.
2. **Restaurant admin** — Filament, tenant subdomain `/admin`. Menu, settings, reports, receipt printing.
3. **Staff** — React + Inertia, phone-first, installed as a PWA. Order taking and status.
4. **Guest** — React + Inertia, phone-first, no install; arrives by QR and lands on a tile home screen the restaurant arranges (`home_tiles`), walking from there into a menu or a PDF.

Staff are React rather than a third Filament panel because they are on phones: `.ai/rules/filament.md` reserves panels for laptop-and-larger, and order-taking is the highest-frequency screen in the product, where a Livewire round-trip per tap is the wrong trade. Neither React surface works offline — there is no offline requirement, and Inertia needs the server too.

Keeping guests and staff as the only Inertia surfaces is also what keeps their bundles free of Filament assets. Do not import Filament into either, and do not add a Filament panel for a phone audience.

## The menu is four levels, and a guest lands on tiles rather than on it
`menus` → `menu_categories` → `menu_items` → `menu_item_additions`. A restaurant that serves one card all day simply keeps one menu; one that serves a different card at lunch has two. Additions are a flat list of extras per dish (name plus a price delta, and zero is a real price — "no onions" costs nothing and is still worth listing). Grouped choices with rules — "pick exactly one size", "up to three toppings" — are a real thing a menu eventually needs and belong in a layer **above** `menu_item_additions`, not inside it.

What a guest sees first is `home_tiles`: rectangular image tiles the restaurant orders by hand, each opening a menu or a PDF. That is why `/` is the tile home and a menu lives at `/menus/{menu}`.

## Two languages, English default, and the visitor picks
`App\Enums\Locale` has one case per language the app is available in — English and Tamil for now — and is the single source of truth: the cookie middleware validates against it, the toggle is built from it, and every translated column stores one key per case. English is the default, the fallback, and the only language an admin form requires.

Like the India assumption above, this is a "for now": add a language by adding a case and a `lang/<value>` directory, not by branching on a locale at a call site. The one thing that does not scale for free is the expression unique indexes, which are built on English — see `.ai/rules/migrations.md`.
