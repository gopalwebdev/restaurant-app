/**
 * The props HandleTenantInertiaRequests shares with every page of both apps.
 *
 * Both the guest app and the staff app are served by the same middleware, so
 * both receive exactly this. Anything a single page needs is declared on that
 * page instead.
 */

export type LocaleOption = {
    value: string;
    label: string;
    shortLabel: string;
};

export type LocaleProp = {
    /** The language this page was rendered in. */
    current: string;
    /** The one the toggle switches to, decided server-side. */
    next: string;
    available: LocaleOption[];
};

/**
 * A tree of translated strings, as the lang/ file was written.
 *
 * Nested because the PHP file is: `guest.menu.empty` arrives as
 * `{ menu: { empty: '...' } }` and is read with a dotted path.
 */
export type Translations = {
    [key: string]: string | Translations;
};

export type TenantSharedProps = {
    restaurant: { name: string; slug: string } | null;
    auth: { user: { name: string; email: string } | null };
    locale: LocaleProp;
    translations: Translations;
    /** What the server painted the page with: 'system', 'light' or 'dark'. */
    appearance: string;
};
