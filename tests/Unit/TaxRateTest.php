<?php

use App\Enums\TaxRate;

/*
|--------------------------------------------------------------------------
| GST arithmetic
|--------------------------------------------------------------------------
|
| A unit test rather than a feature one: this is pure integer arithmetic with
| no database behind it, and it is the piece a bill is built out of, so it
| should be fast enough to run on every change.
|
*/

it('reads a slab as the percentage a menu prints', function (): void {
    expect(TaxRate::Zero->label())->toBe('0%')
        ->and(TaxRate::Five->label())->toBe('5%')
        ->and(TaxRate::Twelve->label())->toBe('12%')
        ->and(TaxRate::TwentyEight->label())->toBe('28%');
});

it('defaults to the standalone restaurant slab', function (): void {
    // 5% without input tax credit is what almost every restaurant on this
    // platform charges, and it is what a new dish falls back to.
    expect(TaxRate::default())->toBe(TaxRate::Five);
});

it('adds tax on top of a price that does not include it', function (): void {
    expect(TaxRate::Five->taxOn(10000))->toBe(500)
        ->and(TaxRate::Five->totalOn(10000))->toBe(10500)
        ->and(TaxRate::Eighteen->taxOn(10000))->toBe(1800);
});

it('finds the tax already inside a price that includes it', function (): void {
    // The classic GST bug is running this the wrong way round: 5% *of* 105 is
    // 5.25, but the tax *inside* 105 is exactly 5.00, and a bill that reports
    // the first will not reconcile.
    expect(TaxRate::Five->taxOn(10500, priceIncludesTax: true))->toBe(500)
        ->and(TaxRate::Eighteen->taxOn(11800, priceIncludesTax: true))->toBe(1800);
});

it('leaves a tax-inclusive price as its own total', function (): void {
    expect(TaxRate::Five->totalOn(10500, priceIncludesTax: true))->toBe(10500);
});

it('charges nothing at all on the zero slab', function (): void {
    expect(TaxRate::Zero->taxOn(99999))->toBe(0)
        ->and(TaxRate::Zero->taxOn(99999, priceIncludesTax: true))->toBe(0)
        ->and(TaxRate::Zero->totalOn(99999))->toBe(99999);
});

it('rounds to a whole minor unit and stays an integer', function (): void {
    // ₹99.99 at 5% is 4.9995 rupees of tax, which has to land on a paisa.
    expect(TaxRate::Five->taxOn(9999))->toBe(500)
        ->and(TaxRate::Five->taxOn(9999))->toBeInt()
        ->and(TaxRate::Twelve->taxOn(1))->toBe(0);
});

it('splits a slab into the two halves an intra-state invoice prints', function (): void {
    // A dine-in sale is always intra-state, so 5% is charged once and shown as
    // CGST 2.5% plus SGST 2.5%. Every slab is even in basis points, so the two
    // halves always add back to the whole.
    foreach (TaxRate::cases() as $rate) {
        expect($rate->halfBasisPoints() * 2)->toBe($rate->value);
    }

    expect(TaxRate::Five->halfBasisPoints())->toBe(250);
});

it('offers every slab to a select, keyed by what gets stored', function (): void {
    expect(TaxRate::options())->toBe([
        0 => '0%',
        500 => '5%',
        1200 => '12%',
        1800 => '18%',
        2800 => '28%',
    ]);
});
