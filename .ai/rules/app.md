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
