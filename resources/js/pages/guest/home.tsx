import { Head, Link } from '@inertiajs/react';

import { AppBar } from '@/components/app-bar';
import { useTranslations } from '@/hooks/use-translations';

interface Tile {
    id: number;
    label: string;
    shape: string;
    /** The CSS aspect ratio the stored shape is drawn at, decided server-side. */
    aspectRatio: string;
    imageUrl: string | null;
    href: string;
}

interface HomeProps {
    restaurant: { name: string; slug: string } | null;
    tiles: Tile[];
}

/**
 * What a guest sees after scanning the QR code at their table.
 *
 * The restaurant arranged this — the pictures, the order, and what each tile
 * opens — so this page draws whatever it is given and decides nothing. One
 * column of wide tiles, sized for a thumb, no hover anywhere.
 */
export default function Home({ restaurant, tiles }: HomeProps) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={restaurant?.name ?? t('home.title')} />

            <AppBar title={restaurant?.name ?? t('home.title')} />

            <main className="flex-1 px-4 pt-4 pb-[max(2rem,env(safe-area-inset-bottom))]">
                {tiles.length === 0 ? (
                    <p className="text-muted-foreground px-1 py-16 text-center text-sm">
                        {t('home.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {tiles.map((tile) => (
                            <li key={tile.id}>
                                <Link
                                    href={tile.href}
                                    className="focus-visible:ring-ring block overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <HomeTile tile={tile} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

/**
 * One tile.
 *
 * A tile with no picture is not broken — it is a restaurant that has arranged
 * its home screen before it has photography — so it is drawn as its label on
 * the brand colour rather than as an empty box.
 */
function HomeTile({ tile }: { tile: Tile }) {
    if (tile.imageUrl === null) {
        return (
            <div
                className="bg-primary text-primary-foreground flex items-center justify-center px-4"
                style={{ aspectRatio: tile.aspectRatio }}
            >
                <span className="text-center text-lg font-semibold">
                    {tile.label}
                </span>
            </div>
        );
    }

    return (
        <div className="relative" style={{ aspectRatio: tile.aspectRatio }}>
            <img
                src={tile.imageUrl}
                alt={tile.label}
                className="size-full object-cover"
                loading="lazy"
            />

            {/* The label sits over the picture rather than under it, so the
                tile stays one tap target and the name is readable whatever the
                photograph is. */}
            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-8 pb-3">
                <span className="text-lg font-semibold text-white drop-shadow">
                    {tile.label}
                </span>
            </div>
        </div>
    );
}
