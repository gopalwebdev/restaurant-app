import { createInertiaApp } from '@inertiajs/react';

import { MobileFrame } from '@/layouts/mobile-frame';

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
    progress: { color: 'var(--primary)' },
});
