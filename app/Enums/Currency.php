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
