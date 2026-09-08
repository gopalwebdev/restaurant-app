---
paths:
  - 'resources/js/**'
---

# Js

## Vitest specs live in resources/js/tests, never beside a page
`vp test` (Vitest, configured in the `test` block of vite.config.ts) only collects `resources/js/tests/**/*.test.{ts,tsx}`.

Do not co-locate a spec inside `resources/js/pages/` — Inertia resolves every file under that directory as a page component, so `welcome.test.tsx` would register as a page named "welcome.test" and get bundled into the production build.

`resources/js/tests/setup.ts` stubs Inertia's `<Head>`, which otherwise throws because its head manager only exists after createInertiaApp has run.

## React is the phone lane: guests and staff both
Both React surfaces are designed at phone width and judged there. Lay out for one narrow column, size tap targets for thumbs, and never rely on hover to make something usable. Desktop breakpoints are not the job: reach for sm:/md:/lg: only to stop a page looking stretched on a wider screen, never to build a second layout, and never let a phone carry the cost of one.

Staff are here rather than in a Filament panel on purpose. They work one-handed at speed on a busy floor, and order-taking is the highest-frequency screen in the product — every Livewire interaction is a server round-trip, which is exactly the wrong trade there. It also keeps .ai/rules/filament.md's laptop-and-larger rule honest instead of carving a phone-shaped exception into it.

Staff install their surface as a PWA and keep it; guests arrive by QR and leave, so guests get no install prompt. Neither works offline — there is no offline requirement, and Inertia needs the server just as Livewire does. The PWA buys a home-screen icon and fullscreen chrome, not offline ordering; do not design for a network that is not there.
