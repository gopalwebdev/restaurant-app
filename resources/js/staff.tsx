import { createInertiaApp } from '@inertiajs/react';

import { initializeTheme } from '@/hooks/use-appearance';
import { MobileFrame } from '@/layouts/mobile-frame';

// Adopt whatever the server already painted; see the note in guest.tsx.
initializeTheme();

/**
 * The staff app.
 *
 * `pages` scopes this entry to pages/staff and nothing else, so Vite gives the
 * two apps separate graphs: a phone loading the staff app never downloads a
 * byte of the guest app, and neither touches Filament, which the panels serve
 * from their own compiled assets.
 */
void createInertiaApp({
    pages: './pages/staff',
    title: (title) => (title ? `${title} · Staff` : 'Staff'),
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
