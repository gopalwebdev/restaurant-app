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
