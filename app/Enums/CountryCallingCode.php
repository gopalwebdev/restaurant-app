<?php

namespace App\Enums;

/**
 * The country codes a phone number may be dialled with.
 *
 * Backing values carry the leading plus, as E.164 writes them, so a stored
 * value reads as what it is. It also keeps the value a string wherever it is
 * used as an array key: PHP turns a numeric string key such as '91' into the
 * integer 91, and a select's options would then disagree with the column.
 *
 * The platform serves India for now, so there is one case. The column is wide
 * enough for any calling code; adding a country here is all that stands
 * between this and a second one, save for the length of its numbers.
 */
enum CountryCallingCode: string
{
    case India = '+91';

    /**
     * How many digits a mobile number carries in this country.
     */
    public function mobileNumberLength(): int
    {
        return match ($this) {
            self::India => 10,
        };
    }

    /**
     * The country's name.
     */
    public function countryName(): string
    {
        return match ($this) {
            self::India => 'India',
        };
    }

    /**
     * The code as it is written in front of a number.
     */
    public function dialPrefix(): string
    {
        return $this->value;
    }

    /**
     * The calling code as bare digits, the way a stored number spells it.
     */
    public function digits(): string
    {
        return mb_ltrim($this->value, '+');
    }

    /**
     * The calling code these digits name, if any.
     */
    public static function fromDigits(string $digits): ?self
    {
        return self::tryFrom('+'.$digits);
    }

    /**
     * The name shown when choosing a country code.
     */
    public function label(): string
    {
        return sprintf('%s (%s)', $this->countryName(), $this->value);
    }

    /**
     * Every calling code, keyed by its stored value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $code) {
            $options[$code->value] = $code->label();
        }

        return $options;
    }

    /**
     * The longest mobile number any supported country has.
     *
     * The phone columns are sized to this, so a country whose numbers are
     * longer needs a migration as well as a case above.
     */
    public static function longestMobileNumberLength(): int
    {
        return max(array_map(
            static fn (self $code): int => $code->mobileNumberLength(),
            self::cases(),
        ));
    }
}
