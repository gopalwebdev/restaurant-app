import { MoonIcon, SunIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslations } from '@/hooks/use-translations';

/**
 * One button, light or dark.
 *
 * Light and dark are the whole of the theming, so one button says all there is
 * to say. The icon shows the destination rather than the current state — a moon
 * means tapping gives you dark.
 *
 * The choice is kept on the phone, in localStorage and a cookie, so it survives
 * the next scan of the same QR code and the server can paint the next first
 * response the same way before React runs.
 */
export function ThemeToggle() {
    const { appearance, toggleAppearance } = useAppearance();
    const { t } = useTranslations();

    const goingDark = appearance === 'light';
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
