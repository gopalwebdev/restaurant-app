import { Form, Head } from '@inertiajs/react';

import { FoodTypeDot, type FoodType } from '@/components/food-type-dot';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import session from '@/routes/staff/session';

interface Item {
    id: number;
    name: string;
    price: string;
    foodType: FoodType;
    isAvailable: boolean;
}

interface Section {
    name: string;
    items: Item[];
}

interface HomeProps {
    restaurant: { name: string; slug: string } | null;
    auth: { user: { name: string; email: string } | null };
    sections: Section[];
}

/**
 * The staff app's home screen.
 *
 * Orders are what this app is for and they do not exist in the schema yet, so
 * this shows the other thing the floor is asked all evening: what is on, and
 * what has run out. Unlike the guest menu it lists sold-out dishes too, marked
 * — staff need to know what to say no to.
 */
export default function Home({ restaurant, auth, sections }: HomeProps) {
    return (
        <>
            <Head title="Today" />

            <header className="bg-background/95 sticky top-0 z-10 border-b px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-4 backdrop-blur">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-muted-foreground truncate text-xs">
                            {auth.user?.name}
                        </p>
                        <h1 className="truncate text-xl leading-tight font-semibold tracking-tight">
                            {restaurant?.name}
                        </h1>
                    </div>

                    <Form
                        action={session.destroy(restaurant?.slug ?? '')}
                        className="shrink-0"
                    >
                        <Button type="submit" variant="ghost" size="sm">
                            Sign out
                        </Button>
                    </Form>
                </div>
            </header>

            <main className="flex-1 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {sections.map((section) => (
                    <section key={section.name} className="pt-6">
                        <h2 className="text-muted-foreground px-5 text-xs font-semibold tracking-widest uppercase">
                            {section.name}
                        </h2>

                        <ul className="mt-2">
                            {section.items.map((item, index) => (
                                <li key={item.id}>
                                    {index > 0 && (
                                        <Separator className="ml-5" />
                                    )}
                                    <div className="flex items-center gap-3 px-5 py-3.5">
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
                                            <Badge variant="secondary">
                                                Sold out
                                            </Badge>
                                        )}
                                        <span className="shrink-0 font-semibold tabular-nums">
                                            {item.price}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </main>
        </>
    );
}
