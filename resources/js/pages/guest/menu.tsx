import { Head } from '@inertiajs/react';

import { FoodTypeDot, type FoodType } from '@/components/food-type-dot';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

interface MenuItem {
    id: number;
    name: string;
    description: string | null;
    price: string;
    foodType: FoodType;
}

interface Section {
    id: number;
    name: string;
    items: MenuItem[];
}

interface MenuProps {
    restaurant: { name: string; slug: string } | null;
    sections: Section[];
    acceptingOrders: boolean;
}

/**
 * The menu a guest reads at the table.
 *
 * One narrow column, thumb-sized rows, no hover anywhere: a guest is holding a
 * phone in one hand. Only orderable dishes arrive here, so there is nothing
 * greyed out to scroll past.
 */
export default function Menu({
    restaurant,
    sections,
    acceptingOrders,
}: MenuProps) {
    return (
        <>
            <Head title={restaurant?.name ?? 'Menu'} />

            <header className="bg-background/95 sticky top-0 z-10 border-b px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-4 backdrop-blur">
                <div className="flex items-start justify-between gap-3">
                    <h1 className="text-xl leading-tight font-semibold tracking-tight">
                        {restaurant?.name ?? 'Menu'}
                    </h1>
                    <Badge variant={acceptingOrders ? 'default' : 'secondary'}>
                        {acceptingOrders ? 'Open' : 'Closed'}
                    </Badge>
                </div>
            </header>

            <main className="flex-1 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {sections.length === 0 ? (
                    <p className="text-muted-foreground px-5 py-16 text-center text-sm">
                        This menu is not ready yet. Please ask a member of
                        staff.
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
                                        <article className="flex items-start gap-3 px-5 py-4">
                                            <FoodTypeDot
                                                type={item.foodType}
                                                className="mt-1"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <h3 className="leading-snug font-medium">
                                                    {item.name}
                                                </h3>
                                                {item.description && (
                                                    <p className="text-muted-foreground mt-1 text-sm leading-snug">
                                                        {item.description}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="text-primary shrink-0 font-semibold tabular-nums">
                                                {item.price}
                                            </p>
                                        </article>
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
