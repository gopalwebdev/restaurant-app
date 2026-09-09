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
