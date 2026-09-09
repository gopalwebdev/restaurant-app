import { Form, Head } from '@inertiajs/react';

import { AppBar } from '@/components/app-bar';
import { FoodTypeDot, type FoodType } from '@/components/food-type-dot';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';
import session from '@/routes/staff/session';

interface Addition {
    id: number;
    name: string;
    price: string | null;
    isAvailable: boolean;
}

interface Item {
    id: number;
    name: string;
    price: string;
    foodType: FoodType;
    isAvailable: boolean;
    additions: Addition[];
}

interface Section {
    id: number;
    name: string;
    items: Item[];
}

interface Menu {
    id: number;
    name: string;
    isActive: boolean;
    sections: Section[];
}

interface HomeProps {
    restaurant: { name: string; slug: string } | null;
    auth: { user: { name: string; email: string } | null };
    menus: Menu[];
}

/**
 * The staff app's home screen.
 *
 * Orders are what this app is for and they do not exist in the schema yet, so
 * this shows the other thing the floor is asked all evening: what is on, what
 * has run out, and what a dish can be ordered with. Unlike the guest menu it
 * lists hidden menus, hidden sections, sold-out dishes and unavailable
 * additions too, all marked — staff need to know what to say no to.
 */
export default function Home({ restaurant, auth, menus }: HomeProps) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('home.title')} />

            <AppBar
                title={restaurant?.name ?? t('home.title')}
                eyebrow={auth.user?.name}
            >
                <Form
                    action={session.destroy(restaurant?.slug ?? '')}
                    className="contents"
                >
                    <Button type="submit" variant="ghost" size="sm">
                        {t('actions.sign_out')}
                    </Button>
                </Form>
            </AppBar>

            <main className="flex-1 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {menus.length === 0 ? (
                    <p className="text-muted-foreground px-5 py-16 text-center text-sm">
                        {t('home.empty')}
                    </p>
                ) : (
                    menus.map((menu) => (
                        <section key={menu.id} className="pt-5">
                            <div className="flex items-center gap-2 px-5">
                                <h2 className="text-sm font-semibold tracking-tight">
                                    {menu.name}
                                </h2>
                                {!menu.isActive && (
                                    <Badge variant="secondary">
                                        {t('item.sold_out')}
                                    </Badge>
                                )}
                            </div>

                            {menu.sections.map((section) => (
                                <div key={section.id} className="pt-3">
                                    <h3 className="text-muted-foreground px-5 text-xs font-semibold tracking-widest uppercase">
                                        {section.name}
                                    </h3>

                                    <ul className="mt-1">
                                        {section.items.map((item, index) => (
                                            <li key={item.id}>
                                                {index > 0 && (
                                                    <Separator className="ml-5" />
                                                )}
                                                <Dish item={item} />
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </section>
                    ))
                )}
            </main>
        </>
    );
}

/**
 * One dish, its price, and the extras it can be ordered with.
 */
function Dish({ item }: { item: Item }) {
    const { t } = useTranslations();

    return (
        <div className="px-5 py-3">
            <div className="flex items-center gap-3">
                <FoodTypeDot type={item.foodType} />

                <span
                    className={
                        item.isAvailable
                            ? 'min-w-0 flex-1 font-medium'
                            : 'text-muted-foreground min-w-0 flex-1 font-medium line-through'
                    }
                >
                    {item.name}
                </span>

                {!item.isAvailable && (
                    <Badge variant="secondary">{t('item.sold_out')}</Badge>
                )}

                <span className="shrink-0 font-semibold tabular-nums">
                    {item.price}
                </span>
            </div>

            {item.additions.length > 0 && (
                <ul className="mt-1.5 ml-7 flex flex-wrap gap-x-3 gap-y-1">
                    {item.additions.map((addition) => (
                        <li
                            key={addition.id}
                            className={
                                addition.isAvailable
                                    ? 'text-muted-foreground text-sm'
                                    : 'text-muted-foreground/70 text-sm line-through'
                            }
                        >
                            {addition.name}{' '}
                            <span className="tabular-nums">
                                {addition.price === null
                                    ? t('item.free')
                                    : `+ ${addition.price}`}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
