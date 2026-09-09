---
paths:
  - 'app/Http/Middleware/**'
  - app/Http/Middleware/SetLocale.php
---

# Middleware

## Signed in is not the same as works here
Accounts are platform-wide but sessions are per domain, so `auth` alone does **not** stop someone who works at one restaurant from opening another's staff app by editing the subdomain. `EnsureStaffMemberWorksHere` is what does, and every authenticated staff route needs it alongside `auth`.

It delegates to `AuthenticateStaffMember::mayWorkHere()`, the same check `SignInController` runs on the way in — one definition of "may work this floor" (on the roster, and holds `menu.view`), applied at sign-in and on every request after.

This was a real hole caught by a test, not a hypothetical: `/staff` returned 200 for another restaurant's staff before the middleware existed.

Guests hitting an authenticated staff route are redirected per tenant by `redirectGuestsTo` in `bootstrap/app.php`, which must handle the domain parameter arriving as either a resolved `Restaurant` or a raw slug string depending on where in the stack it is reached.

## SetLocale runs first in the web group, and its cookie is untrusted
SetLocale is appended to the `web` group in bootstrap/app.php **before** HandleInertiaRequests, so everything downstream — the Inertia props, the translated columns a controller reads, the root template's `lang` attribute — renders in the language it chose. Moving it after the Inertia middleware silently serves English props on a Tamil page.

Its cookie is listed in `encryptCookies(except: [...])` alongside `appearance`, because the toggle in React has to read the current value before the server can tell it. That makes both cookies visitor-controlled, so neither is ever trusted: a value that is not a known App\Enums\Locale case falls back to `config('app.locale')` and then to English, and `$request->cookie()` can return an array, so it is type-checked rather than cast.
