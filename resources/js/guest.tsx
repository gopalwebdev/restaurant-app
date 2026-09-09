import { createInertiaApp } from '@inertiajs/react';

import { MobileFrame } from '@/layouts/mobile-frame';

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
    progress: { color: 'var(--primary)' },
});
