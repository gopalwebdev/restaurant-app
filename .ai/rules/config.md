---
paths:
  - 'config/**'
---

# Config

## Valkey backs cache, session and queue; Horizon runs the workers
Valkey is Redis wire-compatible, so `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` are all `redis` against 127.0.0.1:6379 via phpredis. There is no separate Valkey driver — do not go looking for one.

Queued work needs `php artisan horizon` running; nothing is delivered without it. Supervisor queues are `['mail', 'default']` in that order, and `/horizon` is gated on the super-admin role in HorizonServiceProvider.

phpunit.xml pins tests to array cache/session and the sync queue, so tests never touch Valkey. If tests start behaving as though they share state, check for a stale `bootstrap/cache/config.php` — a cached config silently overrides every phpunit.xml env var. `php artisan optimize:clear` fixes it, and nothing in local development should leave those caches in place.

## DB_TIMEZONE must be a named zone, never an offset
The application runs in Asia/Kolkata, set from `APP_TIMEZONE` in .env, and the pgsql connection is given the same zone through `DB_TIMEZONE` so `now()` in PHP and `now()` in SQL agree. phpunit.xml pins APP_TIMEZONE too, so tests behave as production does.

The trap: it has to be the named zone, not an offset. Laravel issues `SET TIME ZONE '<value>'`, and Postgres reads a bare `+05:30` with POSIX sign conventions — positive means *west* of Greenwich — so it silently lands on UTC-5:30, eleven hours out, with no error. Verified: `+05:30` gives `00:50:05-05:30` where `Asia/Kolkata` gives `11:50:05+05:30`.

Never hardcode a zone or an offset in code; both come from the environment.
