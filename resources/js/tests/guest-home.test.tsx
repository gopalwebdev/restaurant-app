import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import Home from '@/pages/guest/home';

const restaurant = { name: 'Spice Garden', slug: 'spice' };

function tile(
    overrides: Partial<Parameters<typeof Home>[0]['tiles'][number]> = {},
) {
    return {
        id: 1,
        label: 'Menu',
        shape: 'rectangle',
        aspectRatio: '16 / 9',
        imageUrl: 'http://spice.restaurant-app.test/tiles/1/image',
        href: 'http://spice.restaurant-app.test/menus/1',
        ...overrides,
    };
}

describe('guest home', () => {
    it('names the restaurant at the top', () => {
        render(<Home restaurant={restaurant} tiles={[tile()]} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Spice Garden',
        );
    });

    it('draws the tiles in the order the restaurant arranged them', () => {
        render(
            <Home
                restaurant={restaurant}
                tiles={[
                    tile({ id: 1, label: 'Menu' }),
                    tile({ id: 2, label: 'Drinks' }),
                    tile({ id: 3, label: 'Offers' }),
                ]}
            />,
        );

        // The server has already sorted them; the page must not re-sort.
        const links = screen.getAllByRole('link');

        expect(links).toHaveLength(3);
        expect(links[0]).toHaveTextContent('Menu');
        expect(links[1]).toHaveTextContent('Drinks');
        expect(links[2]).toHaveTextContent('Offers');
    });

    it('sends each tile where the server said it goes', () => {
        render(
            <Home
                restaurant={restaurant}
                tiles={[
                    tile({
                        id: 7,
                        label: 'Wine list',
                        href: 'http://spice.restaurant-app.test/tiles/7',
                    }),
                ]}
            />,
        );

        expect(screen.getByRole('link')).toHaveAttribute(
            'href',
            expect.stringContaining('/tiles/7'),
        );
    });

    it("labels a tile's picture for a screen reader", () => {
        render(
            <Home restaurant={restaurant} tiles={[tile({ label: 'Menu' })]} />,
        );

        expect(screen.getByRole('img', { name: 'Menu' })).toBeInTheDocument();
    });

    it('draws a tile with no picture as its label rather than an empty box', () => {
        render(
            <Home
                restaurant={restaurant}
                tiles={[tile({ label: 'Drinks', imageUrl: null })]}
            />,
        );

        expect(screen.queryByRole('img')).not.toBeInTheDocument();
        expect(screen.getByText('Drinks')).toBeInTheDocument();
    });

    it('says so plainly when the restaurant has arranged nothing yet', () => {
        render(<Home restaurant={restaurant} tiles={[]} />);

        expect(screen.getByText(/no home screen yet/i)).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
