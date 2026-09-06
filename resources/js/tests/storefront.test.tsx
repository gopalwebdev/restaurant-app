import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';
import Storefront from '@/pages/storefront';

describe('storefront', () => {
    const restaurant = { name: 'Tenant One', slug: 't1' };

    it('names the restaurant it is serving', () => {
        render(<Storefront restaurant={restaurant} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Tenant One' }),
        ).toBeInTheDocument();
        expect(screen.getByText('t1')).toBeInTheDocument();
    });

    it('lays out a section for each part of the storefront still to come', () => {
        render(<Storefront restaurant={restaurant} />);

        for (const section of ['Menu', 'Offers', 'Cart']) {
            expect(screen.getByText(section)).toBeInTheDocument();
        }

        expect(screen.getAllByText('Not built yet.')).toHaveLength(3);
    });

    it('marks the restaurant as open', () => {
        render(<Storefront restaurant={restaurant} />);

        expect(screen.getByText('Open')).toBeInTheDocument();
    });
});
