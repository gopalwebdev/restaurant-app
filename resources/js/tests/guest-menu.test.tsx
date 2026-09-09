import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';

import Menu from '@/pages/guest/menu';

const restaurant = { name: 'Spice Garden', slug: 'spice' };
const menu = { id: 1, name: 'Dinner', description: null };
const homeUrl = 'http://spice.restaurant-app.test';

describe('guest menu', () => {
    it('names the menu and says whether the restaurant is taking orders', () => {
        render(
            <Menu
                restaurant={restaurant}
                menu={menu}
                sections={[]}
                acceptingOrders
                homeUrl={homeUrl}
            />,
        );

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Dinner',
        );
        expect(screen.getByText('Spice Garden')).toBeInTheDocument();
        expect(screen.getByText('Open')).toBeInTheDocument();
    });

    it('offers a way back to the tiles the guest came in through', () => {
        render(
            <Menu
                restaurant={restaurant}
                menu={menu}
                sections={[]}
                acceptingOrders
                homeUrl={homeUrl}
            />,
        );

        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute(
            'href',
            expect.stringContaining('spice.restaurant-app.test'),
        );
    });

    it('lists each dish with its price and diet mark', () => {
        render(
            <Menu
                restaurant={restaurant}
                menu={menu}
                acceptingOrders
                homeUrl={homeUrl}
                sections={[
                    {
                        id: 1,
                        name: 'Starters',
                        items: [
                            {
                                id: 10,
                                name: 'Paneer Tikka',
                                description: 'Charred in the tandoor.',
                                priceMinorUnits: 24950,
                                foodType: 'vegetarian',
                                additions: [],
                            },
                        ],
                    },
                ]}
            />,
        );

        expect(screen.getByText('Starters')).toBeInTheDocument();
        expect(screen.getByText('Paneer Tikka')).toBeInTheDocument();
        // The price arrives as the integer 24950 and is turned into money
        // here, in the guest's own language.
        expect(screen.getByText(/249\.50/)).toBeInTheDocument();
        // The veg/non-veg mark is a regulatory one, so it carries a label
        // rather than being colour alone.
        expect(screen.getByLabelText('Vegetarian')).toBeInTheDocument();
    });

    it("lists a dish's additions, and names the free ones rather than pricing them", () => {
        render(
            <Menu
                restaurant={restaurant}
                menu={menu}
                acceptingOrders
                homeUrl={homeUrl}
                sections={[
                    {
                        id: 1,
                        name: 'Starters',
                        items: [
                            {
                                id: 10,
                                name: 'Paneer Tikka',
                                description: null,
                                priceMinorUnits: 24950,
                                foodType: 'vegetarian',
                                additions: [
                                    {
                                        id: 100,
                                        name: 'Extra paneer',
                                        priceMinorUnits: 5000,
                                    },
                                    {
                                        id: 101,
                                        name: 'Less spicy',
                                        priceMinorUnits: 0,
                                    },
                                ],
                            },
                        ],
                    },
                ]}
            />,
        );

        expect(screen.getByText('Add to this')).toBeInTheDocument();
        expect(screen.getByText(/\+\s*₹?50\.00/)).toBeInTheDocument();
        // "₹0.00" beside a choice that simply costs nothing reads as a mistake.
        expect(screen.getByText('Free')).toBeInTheDocument();
        expect(screen.queryByText(/₹0\.00/)).not.toBeInTheDocument();
    });

    it('says so plainly when there is no menu yet', () => {
        render(
            <Menu
                restaurant={restaurant}
                menu={menu}
                sections={[]}
                acceptingOrders={false}
                homeUrl={homeUrl}
            />,
        );

        expect(screen.getByText(/not ready yet/i)).toBeInTheDocument();
        expect(screen.getByText('Closed')).toBeInTheDocument();
    });
});
