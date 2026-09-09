---
paths:
  - 'resources/views/**'
---

# Views

## Theme is injected into the root template, never sent as a prop
A restaurant's brand colour and light/dark choice live on `restaurant_settings` and are written into the HTML of the first response by `resources/views/partials/theme.blade.php`, which overrides the shadcn CSS variables `--primary` / `--ring` on `:root`.

It has to be there, not in an Inertia prop: React runs after the first paint, so a prop would show the default colour and then visibly correct itself in front of the guest.

`HandleTenantInertiaRequests` shares two view variables for this — `$theme` and `$tenantSlug`. The slug is required because every tenant route carries `{restaurant}` in its **domain**, so `route('staff.manifest')` without it throws `UrlGenerationException`. Any new tenant URL built in a Blade template needs `['restaurant' => $tenantSlug]`.
