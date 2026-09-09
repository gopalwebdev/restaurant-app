import { Head } from '@inertiajs/react';

import { AppBar } from '@/components/app-bar';
import { FoodTypeDot, type FoodType } from '@/components/food-type-dot';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useMoney } from '@/hooks/use-money';
import { useTranslations } from '@/hooks/use-translations';

interface Addition {
    id: number;
    name: string;
    /** An integer count of the currency's minor unit; 0 means free. */
    priceMinorUnits: number;
}

interface MenuItem {
    id: number;
    name: string;
    description: string | null;
    priceMinorUnits: number;
    foodType: FoodType;
    additions: Addition[];
}

interface Section {
    id: number;
    name: string;
    items: MenuItem[];
}

interface MenuProps {
    restaurant: { name: string; slug: string } | null;
    menu: { id: number; name: string; description: string | null };
    sections: Section[];
    acceptingOrders: boolean;
    homeUrl: string;
}

/**
 * One of a restaurant's menus, read at the table.
 *
 * One narrow column, thumb-sized rows, no hover anywhere: a guest is holding a
 * phone in one hand. Only orderable dishes arrive here, so there is nothing
 * greyed out to scroll past — and the back arrow returns to the tiles they came
 * in through.
 *
 * Every name on this page is already in the guest's language: the server picked
 * the translation, falling back to English where a restaurant has not filled
 * one in. Prices arrive as integers and are formatted here, so they follow that
 * same language — see resources/js/lib/money.ts.
 */
export default function Menu({
    restaurant,
    menu,
    sections,
    acceptingOrders,
    homeUrl,
}: MenuProps) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={menu.name} />

            <AppBar
                title={menu.name}
                eyebrow={restaurant?.name}
                backHref={homeUrl}
            >
                <Badge variant={acceptingOrders ? 'default' : 'secondary'}>
                    {acceptingOrders ? t('status.open') : t('status.closed')}
                </Badge>
            </AppBar>

            <main className="flex-1 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {menu.description !== null && (
                    <p className="text-muted-foreground px-5 pt-4 text-sm">
                        {menu.description}
                    </p>
                )}

                {sections.length === 0 ? (
                    <p className="text-muted-foreground px-5 py-16 text-center text-sm">
                        {t('menu.empty')}
                    </p>
                ) : (
                    sections.map((section) => (
                        <section key={section.id} className="pt-6">
                            <h2 className="text-muted-foreground px-5 text-xs font-semibold tracking-widest uppercase">
                                {section.name}
                            </h2>

                            <ul className="mt-2">
                                {section.items.map((item, index) => (
                                    <li key={item.id}>
                                        {index > 0 && (
                                            <Separator className="ml-5" />
                                        )}
                                        <Dish item={item} />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </main>
        </>
    );
}

/**
 * One dish and the extras it can be ordered with.
 */
function Dish({ item }: { item: MenuItem }) {
    const { t } = useTranslations();
    const money = useMoney();

    return (
        <article className="flex items-start gap-3 px-5 py-4">
            <FoodTypeDot type={item.foodType} className="mt-1" />

            <div className="min-w-0 flex-1">
                <h3 className="leading-snug font-medium">{item.name}</h3>

                {item.description !== null && (
                    <p className="text-muted-foreground mt-1 text-sm leading-snug">
                        {item.description}
                    </p>
                )}

                {item.additions.length > 0 && (
                    <div className="mt-2">
                        <p className="text-muted-foreground text-xs font-medium">
                            {t('menu.additions')}
                        </p>

                        <ul className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            {item.additions.map((addition) => (
                                <li
                                    key={addition.id}
                                    className="text-muted-foreground text-sm"
                                >
                                    {addition.name}{' '}
                                    <span className="text-foreground/70 tabular-nums">
                                        {addition.priceMinorUnits === 0
                                            ? t('menu.free')
                                            : `+ ${money(addition.priceMinorUnits)}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            <p className="text-primary shrink-0 font-semibold tabular-nums">
                {money(item.priceMinorUnits)}
            </p>
        </article>
    );
}
