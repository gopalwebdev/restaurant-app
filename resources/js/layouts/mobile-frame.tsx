import type { ReactNode } from 'react';

interface MobileFrameProps {
    children: ReactNode;
}

/**
 * Holds both phone apps at phone width, whatever they are opened on.
 *
 * Guests are on their own phones and staff are on theirs, so a phone is the
 * only size either app is designed for. Opened on a laptop — which happens
 * constantly while building them — the app stays in a phone-shaped column in
 * the middle of the screen rather than stretching into a layout nobody
 * designed. That is deliberately not a desktop breakpoint: there is no second
 * layout to fall back to, and inventing one would be a second design to keep
 * working.
 *
 * Filament is the opposite and belongs on a laptop; see .ai/rules/filament.md.
 */
export function MobileFrame({ children }: MobileFrameProps) {
    return (
        <div className="bg-muted/40 flex min-h-dvh justify-center">
            <div className="bg-background relative flex min-h-dvh w-full max-w-[26rem] flex-col shadow-xl sm:my-0 sm:border-x">
                {children}
            </div>
        </div>
    );
}
