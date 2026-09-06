---
paths:
  - 'resources/js/**'
---

# Js

## Vitest specs live in resources/js/tests, never beside a page
`vp test` (Vitest, configured in the `test` block of vite.config.ts) only collects `resources/js/tests/**/*.test.{ts,tsx}`.

Do not co-locate a spec inside `resources/js/pages/` — Inertia resolves every file under that directory as a page component, so `welcome.test.tsx` would register as a page named "welcome.test" and get bundled into the production build.

`resources/js/tests/setup.ts` stubs Inertia's `<Head>`, which otherwise throws because its head manager only exists after createInertiaApp has run.

## Every page is built for mobile, laptop and larger screens
The storefront and every React page are used on phones as much as on laptops, so lay out mobile-first with Tailwind and add sm:/md:/lg: as the screen grows — never a fixed pixel width, and never a layout only checked at desktop size. Tap targets stay comfortable on touch, tables and wide content scroll inside their own container rather than pushing the page sideways, and nothing depends on hover alone to be usable. Check a narrow viewport before calling a page done.
