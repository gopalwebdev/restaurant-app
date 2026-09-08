<?php

namespace App\Enums;

/**
 * How a menu item is classified for diet.
 *
 * India requires packaged and served food to be marked veg or non-veg — the
 * green and brown dots — and guests filter the menu by it before anything else,
 * so it is a required column on every item rather than an optional tag.
 *
 * @see Role for the note on India being the only market for now
 */
enum FoodType: string
{
    case Vegetarian = 'vegetarian';
    case Egg = 'egg';
    case NonVegetarian = 'non-vegetarian';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Vegetarian',
            self::Egg => 'Contains egg',
            self::NonVegetarian => 'Non-vegetarian',
        };
    }

    /**
     * The colour of the mark shown beside the item, following the Indian
     * convention: green for veg, brown/red for non-veg, amber in between.
     */
    public function color(): string
    {
        return match ($this) {
            self::Vegetarian => 'success',
            self::Egg => 'warning',
            self::NonVegetarian => 'danger',
        };
    }

    /**
     * Every food type, keyed by stored value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $foodType): array {
                $options[$foodType->value] = $foodType->label();

                return $options;
            },
            [],
        );
    }
}
