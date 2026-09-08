<?php

namespace App\Enums;

/**
 * The currencies a restaurant may price its menu in.
 *
 * Backing values are ISO 4217 codes, which is what any payment provider will
 * expect, so the stored value never needs translating.
 */
enum Currency: string
{
    case IndianRupee = 'INR';
    case UnitedStatesDollar = 'USD';
    case Euro = 'EUR';
    case PoundSterling = 'GBP';
    case UaeDirham = 'AED';

    /**
     * The symbol shown next to a price.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::IndianRupee => '₹',
            self::UnitedStatesDollar => '$',
            self::Euro => '€',
            self::PoundSterling => '£',
            self::UaeDirham => 'AED',
        };
    }

    /**
     * How many digits the minor unit has.
     *
     * Money is stored as an integer count of minor units, so this is what turns
     * a stored 24950 into ₹249.50 and back. Every currency here happens to use
     * two, but a match rather than a constant is the point: adding a
     * zero-decimal currency such as JPY must not silently divide by 100.
     */
    public function minorUnitDigits(): int
    {
        return match ($this) {
            self::IndianRupee,
            self::UnitedStatesDollar,
            self::Euro,
            self::PoundSterling,
            self::UaeDirham => 2,
        };
    }

    /**
     * How many minor units make one major unit.
     */
    public function minorUnitsPerMajor(): int
    {
        return 10 ** $this->minorUnitDigits();
    }

    /**
     * Render a stored minor-unit amount as money.
     */
    public function format(int $minorUnits): string
    {
        return $this->symbol().number_format(
            $minorUnits / $this->minorUnitsPerMajor(),
            $this->minorUnitDigits(),
        );
    }

    /**
     * Turn a typed major-unit amount into the integer that gets stored.
     *
     * Rounding happens here, at the edge, and once: everything past this point
     * is exact integer arithmetic.
     */
    public function toMinorUnits(float|int|string $majorUnits): int
    {
        return (int) round(((float) $majorUnits) * $this->minorUnitsPerMajor());
    }

    /**
     * Turn a stored amount back into the major-unit value a form edits.
     */
    public function toMajorUnits(int $minorUnits): float
    {
        return $minorUnits / $this->minorUnitsPerMajor();
    }

    /**
     * The name shown when choosing a currency.
     */
    public function label(): string
    {
        return sprintf('%s (%s)', $this->value, $this->symbol());
    }

    /**
     * Every currency, keyed by its stored value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $currency): array {
                $options[$currency->value] = $currency->label();

                return $options;
            },
            [],
        );
    }
}
