---
paths:
  - 'config/**'
---

# Config

## Valkey backs cache, session and queue; Horizon runs the workers
Valkey is Redis wire-compatible, so `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` are all `redis` against 127.0.0.1:6379 via phpredis. There is no separate Valkey driver — do not go looking for one.

Queued work needs `php artisan horizon` running; nothing is delivered without it. Supervisor queues are `['mail', 'default']` in that order, and `/horizon` is gated on the super-admin role in HorizonServiceProvider.

phpunit.xml pins tests to array cache/session and the sync queue, so tests never touch Valkey. If tests start behaving as though they share state, check for a stale `bootstrap/cache/config.php` — a cached config silently overrides every phpunit.xml env var. `php artisan optimize:clear` fixes it, and nothing in local development should leave those caches in place.
