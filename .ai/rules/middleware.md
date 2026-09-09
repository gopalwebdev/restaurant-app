---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Signed in is not the same as works here
Accounts are platform-wide but sessions are per domain, so `auth` alone does **not** stop someone who works at one restaurant from opening another's staff app by editing the subdomain. `EnsureStaffMemberWorksHere` is what does, and every authenticated staff route needs it alongside `auth`.

It delegates to `AuthenticateStaffMember::mayWorkHere()`, the same check `SignInController` runs on the way in — one definition of "may work this floor" (on the roster, and holds `menu.view`), applied at sign-in and on every request after.

This was a real hole caught by a test, not a hypothetical: `/staff` returned 200 for another restaurant's staff before the middleware existed.

Guests hitting an authenticated staff route are redirected per tenant by `redirectGuestsTo` in `bootstrap/app.php`, which must handle the domain parameter arriving as either a resolved `Restaurant` or a raw slug string depending on where in the stack it is reached.
