import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vite-plus/test';

// Inertia's <Head> reaches for a head manager that only exists once
// createInertiaApp has run. These tests render pages on their own, and nothing
// a page puts in the document head is what they are checking.
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

// React Testing Library leaves its container in the document; without this the
// next test in the file would render into a page that still holds the last one.
afterEach(() => {
    cleanup();
});
