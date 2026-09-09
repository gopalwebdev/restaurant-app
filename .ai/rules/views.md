---
paths:
  - 'resources/views/**'
---

# Views

## Theme is injected into the root template, never sent as a prop
A restaurant's brand colour and light/dark choice live on `restaurant_settings` and are written into the HTML of the first response by `resources/views/partials/theme.blade.php`, which overrides the shadcn CSS variables `--primary` / `--ring` on `:root`.

It has to be there, not in an Inertia prop: React runs after the first paint, so a prop would show the default colour and then visibly correct itself in front of the guest.

The restaurant's choice is a **default**, not a decision: `HandleTenantInertiaRequests::appearanceFor()` prefers the visitor's own `appearance` cookie and falls back to `restaurant_settings.theme_appearance`. The partial also writes the resolved value onto the root element as `data-appearance`, which is how `initializeTheme()` in resources/js/hooks/use-appearance.tsx starts the toggle in the state the page was actually painted in rather than guessing and correcting itself. `initializeTheme()` deliberately persists nothing — only tapping the toggle does, or a first visit would pin the guest to today's setting and silently override the restaurant the next time it changed.

`HandleTenantInertiaRequests` shares two view variables for this — `$theme` and `$tenantSlug`. The slug is required because every tenant route carries `{restaurant}` in its **domain**, so `route('staff.manifest')` without it throws `UrlGenerationException`. Any new tenant URL built in a Blade template needs `['restaurant' => $tenantSlug]`.
