import { MoonIcon, SunIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslations } from '@/hooks/use-translations';

/**
 * One button, light or dark.
 *
 * Not a three-way menu: the restaurant's setting already decides what a guest
 * who has never tapped this sees, so all that is left is "make it the other
 * one". The icon shows the destination, not the current state — a moon means
 * tapping gives you dark.
 *
 * The choice is kept on the phone, in localStorage and a cookie, so it survives
 * the next scan of the same QR code and the server can paint the next first
 * response the same way before React runs.
 */
export function ThemeToggle() {
    const { resolvedAppearance, toggleAppearance } = useAppearance();
    const { t } = useTranslations();

    const goingDark = resolvedAppearance === 'light';
    const label = goingDark
        ? t('actions.switch_to_dark')
        : t('actions.switch_to_light');

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={toggleAppearance}
            aria-label={label}
            title={label}
        >
            {goingDark ? <MoonIcon /> : <SunIcon />}
        </Button>
    );
}
