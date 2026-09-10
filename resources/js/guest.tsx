import { createInertiaApp } from '@inertiajs/react';

import { initializeTheme } from '@/hooks/use-appearance';
import { MobileFrame } from '@/layouts/mobile-frame';

// Adopt whatever the server already painted, so the theme toggle starts in the
// state the guest is looking at. The colour itself was applied before this ran
// — see resources/views/partials/theme.blade.php.
initializeTheme();

/**
 * The guest app.
 *
 * Guests arrive by QR at a table and leave, so there is no install prompt and
 * no service worker — that is the whole difference from the staff app. `pages`
 * keeps this entry to pages/guest, so the two never share a chunk.
 */
void createInertiaApp({
    pages: './pages/guest',
    title: (title) => title ?? '',
    strictMode: true,
    withApp(app) {
        return <MobileFrame>{app}</MobileFrame>;
    },
    // A short delay rather than Inertia's default quarter second: a guest on a
    // phone should see that a tap registered, and the splash in
    // resources/views/partials/boot-loader.blade.php covers the load before
    // this one.
    progress: { color: 'var(--primary)', delay: 100 },
});
