import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { initializeTheme, useAppearance } from '@/hooks/use-appearance';

/**
 * Pin what the operating system claims to prefer.
 */
function stubSystemPreference(prefersDark: boolean): void {
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({
            matches: prefersDark,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        })),
    );
}

/**
 * Stand in for what the server painted the page with.
 *
 * partials/theme.blade.php writes this onto the root element after resolving
 * the visitor's cookie against the restaurant's own setting.
 */
function stubServerAppearance(appearance: string | null): void {
    if (appearance === null) {
        delete document.documentElement.dataset.appearance;

        return;
    }

    document.documentElement.dataset.appearance = appearance;
}

/**
 * The value of the appearance cookie, or an empty string when it has none.
 *
 * Clearing a cookie leaves its name behind with no value, so the name alone
 * does not tell you whether anything was stored.
 */
function storedAppearanceCookie(): string {
    return (
        document.cookie
            .split('; ')
            .find((entry) => entry.startsWith('appearance='))
            ?.slice('appearance='.length) ?? ''
    );
}

describe('use-appearance', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        document.documentElement.style.colorScheme = '';
        document.cookie = 'appearance=;max-age=0;path=/';
        stubServerAppearance(null);
        stubSystemPreference(false);
    });

    it('adopts what the server painted the page with', () => {
        stubServerAppearance('dark');

        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.appearance).toBe('dark');
        expect(document.documentElement).toHaveClass('dark');
    });

    it('stores nothing until the visitor actually chooses', () => {
        stubServerAppearance('dark');

        initializeTheme();

        // Persisting here would pin the visitor to today's setting and quietly
        // override the restaurant the next time it changed its own.
        expect(localStorage.getItem('appearance')).toBeNull();
        expect(storedAppearanceCookie()).toBe('');
    });

    it('falls back to following the system when the server said nothing', () => {
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.appearance).toBe('system');
    });

    it('resolves to dark when the system asks for dark', () => {
        stubSystemPreference(true);
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.resolvedAppearance).toBe('dark');
        expect(document.documentElement).toHaveClass('dark');
    });

    it('overrides the system when a mode is chosen', () => {
        stubSystemPreference(true);
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        act(() => {
            result.current.updateAppearance('light');
        });

        expect(result.current.appearance).toBe('light');
        expect(result.current.resolvedAppearance).toBe('light');
        expect(document.documentElement).not.toHaveClass('dark');
    });

    it('remembers the chosen mode for the next visit', () => {
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        act(() => {
            result.current.updateAppearance('dark');
        });

        expect(localStorage.getItem('appearance')).toBe('dark');
        // The cookie is what lets the server paint the next first response the
        // same way, before React has run.
        expect(document.cookie).toContain('appearance=dark');
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('prefers what the visitor chose over what the server painted', () => {
        localStorage.setItem('appearance', 'dark');
        stubServerAppearance('light');
        stubSystemPreference(false);

        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.appearance).toBe('dark');
        expect(document.documentElement).toHaveClass('dark');
    });

    it('flips to the opposite of what is on screen', () => {
        stubServerAppearance('system');
        stubSystemPreference(true);
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        // "System" resolved to dark, so one tap means light — which is what the
        // guest sees the single button do.
        act(() => {
            result.current.toggleAppearance();
        });

        expect(result.current.appearance).toBe('light');

        act(() => {
            result.current.toggleAppearance();
        });

        expect(result.current.appearance).toBe('dark');
    });

    it('tells every subscriber about a change', () => {
        initializeTheme();

        const first = renderHook(() => useAppearance());
        const second = renderHook(() => useAppearance());

        act(() => {
            first.result.current.updateAppearance('dark');
        });

        expect(second.result.current.appearance).toBe('dark');
    });
});
