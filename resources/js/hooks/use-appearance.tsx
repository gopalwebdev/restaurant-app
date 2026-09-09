import { useSyncExternalStore } from 'react';

/** Light and dark are the whole of the theming — see App\Enums\Appearance. */
export type Appearance = 'light' | 'dark';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly toggleAppearance: () => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'light';

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const isAppearance = (value: unknown): value is Appearance =>
    value === 'light' || value === 'dark';

/**
 * What the server painted the page with.
 *
 * Written onto the root element by resources/views/partials/theme.blade.php
 * from the visitor's own cookie. Reading it back from there — rather than from
 * a prop — is what guarantees the toggle starts in the state the guest is
 * actually looking at.
 */
const getServerAppearance = (): Appearance | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const painted = document.documentElement.dataset.appearance;

    return isAppearance(painted) ? painted : null;
};

const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('appearance');

    return isAppearance(stored) ? stored : null;
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', appearance === 'dark');
    document.documentElement.style.colorScheme = appearance;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

/**
 * Adopt whatever the page is already showing.
 *
 * Deliberately writes nothing. Only tapping the toggle is a choice, and only a
 * choice is worth storing.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentAppearance =
        getStoredAppearance() ?? getServerAppearance() ?? 'light';

    applyTheme(currentAppearance);
    notify();
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'light',
    );

    /**
     * Flip between light and dark.
     *
     * Stored twice on purpose: localStorage for the next visit on this browser,
     * and an unencrypted cookie so the server can paint the next first response
     * the same way before React has run.
     */
    const toggleAppearance = (): void => {
        const next: Appearance = appearance === 'dark' ? 'light' : 'dark';

        currentAppearance = next;
        localStorage.setItem('appearance', next);
        setCookie('appearance', next);

        applyTheme(next);
        notify();
    };

    return { appearance, toggleAppearance } as const;
}
