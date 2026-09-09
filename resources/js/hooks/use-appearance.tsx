import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
    readonly toggleAppearance: () => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const isAppearance = (value: unknown): value is Appearance =>
    value === 'light' || value === 'dark' || value === 'system';

const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = localStorage.getItem('appearance');

    return isAppearance(stored) ? stored : null;
};

/**
 * What the server painted the page with.
 *
 * Written onto the root element by resources/views/partials/theme.blade.php,
 * which has already resolved the visitor's own choice against the restaurant's
 * setting. Reading it back from there — rather than from a prop — is what
 * guarantees the toggle starts in the state the guest is actually looking at.
 */
const getServerAppearance = (): Appearance | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const painted = document.documentElement.dataset.appearance;

    return isAppearance(painted) ? painted : null;
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

/**
 * Adopt whatever the page is already showing.
 *
 * Deliberately writes nothing: a visitor who has never touched the toggle has
 * expressed no preference, and storing one here would pin them to today's
 * setting and quietly override the restaurant the next time it changed its own.
 * Only updateAppearance() persists, because only that is a choice.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentAppearance =
        getStoredAppearance() ?? getServerAppearance() ?? 'system';

    applyTheme(currentAppearance);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    notify();
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;

        // localStorage for the next visit on this browser...
        localStorage.setItem('appearance', mode);

        // ...and a cookie so the server can paint the next first response the
        // same way, before React has run. See partials/theme.blade.php.
        setCookie('appearance', mode);

        applyTheme(mode);
        notify();
    };

    /**
     * Flip between light and dark.
     *
     * The phone apps offer one button rather than a three-way menu, so this
     * resolves "system" against what the phone is actually showing and moves to
     * the opposite of that — which is what the guest sees the button do.
     */
    const toggleAppearance = (): void => {
        updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');
    };

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        toggleAppearance,
    } as const;
}
