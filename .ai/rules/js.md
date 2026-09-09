---
paths:
  - 'resources/js/**'
---

# Js

## Two apps, two entries, two page directories
`resources/js/guest.tsx` and `resources/js/staff.tsx` are separate Inertia entries. Each sets `pages: './pages/guest'` or `'./pages/staff'`, which the `@inertiajs/vite` plugin turns into a resolver scoped to that directory — and that scoping is the whole mechanism keeping the two bundles apart. A page under `pages/staff` is unreachable from the guest entry and vice versa, verified against the built manifest in tests/Feature/Tenant/GuestAppTest.php.

Consequences worth knowing before you touch any of it:

- Component names are relative to the app's own directory: render `'menu'`, not `'guest/menu'`. Both directories are listed in `config/inertia.php` under `pages.paths` so `assertInertia()` can find them.
- Each app has its own root template (`resources/views/{guest,staff}.blade.php`) chosen by its own middleware, `HandleGuestAppRequests` / `HandleStaffAppRequests`. Adding a page to an app means putting it under that app's directory, nothing more.
- Never import across `pages/guest` and `pages/staff`. Shared pieces go in `components/` or `layouts/`, which both may use.

## Vitest specs live in resources/js/tests, never beside a page
`vp test` (Vitest, configured in the `test` block of vite.config.ts) only collects `resources/js/tests/**/*.test.{ts,tsx}`.

Do not co-locate a spec inside `resources/js/pages/` — Inertia resolves every file under that directory as a page component, so `welcome.test.tsx` would register as a page named "welcome.test" and get bundled into the production build.

`resources/js/tests/setup.ts` stubs Inertia's `<Head>`, which otherwise throws because its head manager only exists after createInertiaApp has run.

## React is the phone lane: guests and staff both
Both React surfaces are designed at phone width and judged there. Lay out for one narrow column, size tap targets for thumbs, and never rely on hover to make something usable. Desktop breakpoints are not the job: reach for sm:/md:/lg: only to stop a page looking stretched on a wider screen, never to build a second layout, and never let a phone carry the cost of one.

Staff are here rather than in a Filament panel on purpose. They work one-handed at speed on a busy floor, and order-taking is the highest-frequency screen in the product — every Livewire interaction is a server round-trip, which is exactly the wrong trade there. It also keeps .ai/rules/filament.md's laptop-and-larger rule honest instead of carving a phone-shaped exception into it.

Staff install their surface as a PWA and keep it; guests arrive by QR and leave, so guests get no install prompt. Neither works offline — there is no offline requirement, and Inertia needs the server just as Livewire does. The PWA buys a home-screen icon and fullscreen chrome, not offline ordering; do not design for a network that is not there.

## Both apps carry a theme toggle and a language toggle, and no logo
`components/preference-toggles.tsx` pairs the two things a visitor can change for themselves. It rides in `components/app-bar.tsx` on every screen that has a header, and stands alone at the top of the staff sign-in screen — someone who cannot read that screen cannot get past it to change the language.

The theme is one icon button, light ⇄ dark, and the icon shows the destination rather than the current state. There is no third "system" state and no brand colour — light and dark are the whole of the theming. It is client-side only: `useAppearance().toggleAppearance()` writes localStorage and the `appearance` cookie, and the cookie is what lets the server paint the next first response the same way (see `.ai/rules/views.md`).

The language is a real form `PUT`ing to `preferences.language.update`, not a client-side switch, because half of what a guest reads — dish names, sections, tile labels — is translated in the database and only the server can answer in another language. The server decides which language is next, so the button never holds the list.

There is deliberately **no logo** in either app's chrome. The restaurant's name is text and its brand colour is already on every button and price; a logo slot would be an empty box for every restaurant that has not uploaded one. The PWA manifest keeps the generic app icon, which is what makes the staff app installable.

## Chrome strings come from lang/, dish names come from the page's props
`lang/{en,ta}/guest.php` and `lang/{en,ta}/staff.php` hold each app's chrome and are shared as one `translations` prop; read them with `useTranslations()` and a dotted path, `t('menu.empty')`. Laravel's `:name` placeholders are filled in the browser, so `t('login.code_intro', { length: 6 })` — not a second string with the number baked in.

A missing key falls back to the English one (merged server-side) and then to the path itself, so a half-translated file degrades into a readable screen. `tests/Feature/LocalizationTest.php` asserts the two files have matching keys.

Everything a restaurant wrote — menu, section, dish, addition and tile names — arrives on the page's own props, already in the right language. Never translate those in React.

## Three page directories now, and the guest app has three screens
`pages/guest` holds `home` (the tiles a guest lands on), `menu` (one menu: its featured rail, its combos, its sections with their subdivisions, the dishes in each and their additions, and the small print about tax and charges) and `document` (a tile's PDF, embedded so the app keeps its back arrow).

Two things about the menu screen are worth knowing before editing it. Headings are nested for real — category `h2`, sub-category `h3`, dish `h3` when filed straight under a category and `h4` inside a subdivision — which is why `Dish` takes a `headingLevel`; someone navigating by headings is reading the menu's actual structure. And a rate arrives as **basis points** (500 is 5%), not a percentage, because that is how it is stored so the arithmetic behind a bill stays in integers — the `percentage()` helper turns it into something to read, beside the money formatting and for the same reason. The rule above still holds: component names are relative to the app's own directory, and `pages/guest` and `pages/staff` never import from each other.

Vitest specs render a page directly, outside `createInertiaApp`, so `usePage()` has nowhere to read from. `resources/js/tests/setup.ts` mocks it against `resources/js/tests/page-props.ts`; call `stubPageProps()` to change what a test sees. Its strings are a stand-in, not the real ones — what each app actually says is pinned by `tests/Feature/LocalizationTest.php`.

## Money is formatted here, never in PHP
Prices cross the wire as an integer count of the currency's minor unit — ₹249.50 is `priceMinorUnits: 24950` — exactly as the database stores them, and the restaurant's currency arrives once in the shared `currency` prop as a code and a scale. `useMoney()` turns the two into a string.

Two reasons, both load-bearing. The server does no per-row string building, which is the point of `.ai/rules/general.md`'s memory rule. And `Intl.NumberFormat` follows the guest's own language, so a rupee price groups as ₹2,49,500 rather than ₹249,500 — something `number_format()` cannot do.

Formatters are cached per locale-and-currency in `resources/js/lib/money.ts`, because a menu formats one per row. The scale comes from the server so a zero-decimal currency is never silently divided by 100. The one place money is still formatted in PHP is a Filament table, which is server rendered — it goes through `MenuItem::formattedPrice()`.
