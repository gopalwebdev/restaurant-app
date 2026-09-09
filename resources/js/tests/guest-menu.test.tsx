import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Menu from '@/pages/guest/menu';

const restaurant = { name: 'Spice Garden', slug: 'spice' };

describe('guest menu', () => {
    it('names the restaurant and says whether it is taking orders', () => {
        render(<Menu restaurant={restaurant} sections={[]} acceptingOrders />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Spice Garden',
        );
        expect(screen.getByText('Open')).toBeInTheDocument();
    });

    it('lists each dish with its price and diet mark', () => {
        render(
            <Menu
                restaurant={restaurant}
                acceptingOrders
                sections={[
                    {
                        id: 1,
                        name: 'Starters',
                        items: [
                            {
                                id: 10,
                                name: 'Paneer Tikka',
                                description: 'Charred in the tandoor.',
                                price: '₹249.50',
                                foodType: 'vegetarian',
                            },
                        ],
                    },
                ]}
            />,
        );

        expect(screen.getByText('Starters')).toBeInTheDocument();
        expect(screen.getByText('Paneer Tikka')).toBeInTheDocument();
        expect(screen.getByText('₹249.50')).toBeInTheDocument();
        // The veg/non-veg mark is a regulatory one, so it carries a label
        // rather than being colour alone.
        expect(screen.getByLabelText('Vegetarian')).toBeInTheDocument();
    });

    it('says so plainly when there is no menu yet', () => {
        render(
            <Menu
                restaurant={restaurant}
                sections={[]}
                acceptingOrders={false}
            />,
        );

        expect(screen.getByText(/not ready yet/i)).toBeInTheDocument();
        expect(screen.getByText('Closed')).toBeInTheDocument();
    });
});
