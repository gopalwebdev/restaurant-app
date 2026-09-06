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

describe('use-appearance', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        document.cookie = 'appearance=;max-age=0;path=/';
        stubSystemPreference(false);
    });

    it('starts out following the system', () => {
        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.appearance).toBe('system');
        expect(localStorage.getItem('appearance')).toBe('system');
        expect(document.cookie).toContain('appearance=system');
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
        expect(document.cookie).toContain('appearance=dark');
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('picks up the stored mode rather than the system one', () => {
        localStorage.setItem('appearance', 'dark');
        stubSystemPreference(false);

        initializeTheme();

        const { result } = renderHook(() => useAppearance());

        expect(result.current.appearance).toBe('dark');
        expect(document.documentElement).toHaveClass('dark');
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
