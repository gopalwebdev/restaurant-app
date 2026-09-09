import { LanguageToggle } from '@/components/language-toggle';
import { ThemeToggle } from '@/components/theme-toggle';

/**
 * The two things a visitor can change for themselves: language, and light or
 * dark.
 *
 * Kept together because on a phone they belong in the same corner — there is no
 * sidebar and no settings screen worth the taps, so they ride along in whatever
 * header the screen has. Sign-in has no header, which is why this is its own
 * component rather than living inside AppBar.
 */
export function PreferenceToggles() {
    return (
        <>
            <LanguageToggle />
            <ThemeToggle />
        </>
    );
}
