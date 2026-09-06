import { Head } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface StorefrontProps {
    restaurant: {
        name: string;
        slug: string;
    };
}

/**
 * The public storefront for a single restaurant, resolved from the subdomain.
 *
 * Menus, offers and the cart land in the sections below as they are built.
 */
export default function Storefront({ restaurant }: StorefrontProps) {
    const sections = [
        {
            title: 'Menu',
            description:
                'Dishes grouped by course, with prices and availability.',
        },
        {
            title: 'Offers',
            description:
                'Discounts and combos this restaurant is running today.',
        },
        {
            title: 'Cart',
            description: 'Items picked so far, ready to become an order.',
        },
    ];

    return (
        <>
            <Head title={restaurant.name} />

            <div className="bg-background text-foreground min-h-screen">
                <header className="border-b">
                    <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-8">
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {restaurant.name}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                {restaurant.slug}
                            </p>
                        </div>

                        <Badge variant="secondary">Open</Badge>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-6 py-10">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {sections.map((section) => (
                            <Card key={section.title}>
                                <CardHeader>
                                    <CardTitle>{section.title}</CardTitle>
                                    <CardDescription>
                                        {section.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-sm">
                                        Not built yet.
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </main>
            </div>
        </>
    );
}
