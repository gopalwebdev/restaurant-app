<?php

namespace App\Enums;

/**
 * A GST slab, as basis points.
 *
 * India taxes food at a fixed set of rates rather than any number a restaurant
 * fancies, so this is an enum like every other fixed value set here — see
 * .ai/rules/migrations.md. Prepared food served by a standalone restaurant is
 * 5%; a restaurant inside a hotel with room tariffs above ₹7,500 is 18%; and
 * packaged goods sold alongside — a sealed bottle, a tub of ice cream — carry
 * the rate of the goods themselves, which is where 0, 12 and 28 come in.
 *
 * The backing value is **basis points**, not a percentage: 5% is 500. That
 * keeps the whole tax calculation in integers, exactly as money is stored in
 * minor units (.ai/rules/migrations.md), so no float ever touches a price. A
 * percentage backing would make 12.5% unrepresentable the moment a slab
 * changed, and would put a float in the middle of an amount that has to
 * reconcile with a payment provider to the paisa.
 *
 * Intra-state sales — which a dine-in restaurant always is — split the rate
 * into CGST and SGST, half each. That split is presentation on an invoice, not
 * a second amount: 5% is charged once and printed as 2.5% + 2.5%.
 */
enum TaxRate: int
{
    case Zero = 0;
    case Five = 500;
    case Twelve = 1200;
    case Eighteen = 1800;
    case TwentyEight = 2800;

    /**
     * How many basis points make up one whole.
     *
     * 100% = 10,000 basis points. Named rather than inlined because it appears
     * in every direction of the tax calculation below.
     */
    public const int BASIS_POINTS_PER_WHOLE = 10_000;

    /**
     * The restaurant slab, and the sensible default for a new dish.
     *
     * Standalone restaurant service is 5% without input tax credit, which is
     * what almost every restaurant on this platform charges.
     */
    public static function default(): self
    {
        return self::Five;
    }

    /**
     * The rate as it is written on a menu or an invoice: "5%", "12.5%".
     *
     * Trailing zeros are trimmed, so a whole-number slab does not read "5.00%".
     */
    public function label(): string
    {
        return rtrim(rtrim(number_format($this->value / 100, 2), '0'), '.').'%';
    }

    /**
     * The half of this rate that is CGST on an intra-state invoice.
     *
     * SGST is the same number; a dine-in sale is always intra-state, so IGST
     * does not arise. Every slab here is even in basis points, so the halves
     * are exact and the two of them always add back to the whole.
     */
    public function halfBasisPoints(): int
    {
        return intdiv($this->value, 2);
    }

    /**
     * The tax on an amount, in the same minor units the amount is given in.
     *
     * Which way the arithmetic runs depends on what the price already
     * contains, and getting it backwards is the classic GST bug — 5% *of* 105
     * is 5.25, but the tax *inside* 105 is 5.00:
     *
     * - exclusive: tax = amount × rate
     * - inclusive: tax = amount × rate ÷ (1 + rate)
     *
     * Rounded half-up to the paisa once, at the end, so the result is an exact
     * integer that can be summed without drift.
     */
    public function taxOn(int $minorUnits, bool $priceIncludesTax = false): int
    {
        if ($this === self::Zero) {
            return 0;
        }

        $divisor = $priceIncludesTax
            ? self::BASIS_POINTS_PER_WHOLE + $this->value
            : self::BASIS_POINTS_PER_WHOLE;

        return (int) round($minorUnits * $this->value / $divisor);
    }

    /**
     * What an amount comes to once this rate is added.
     *
     * A price that already includes tax is already the total, which is why
     * this asks rather than assuming.
     */
    public function totalOn(int $minorUnits, bool $priceIncludesTax = false): int
    {
        return $priceIncludesTax
            ? $minorUnits
            : $minorUnits + $this->taxOn($minorUnits);
    }

    /**
     * Every slab, keyed by stored value, for a select field.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $rate): array {
                $options[$rate->value] = $rate->label();

                return $options;
            },
            [],
        );
    }
}
