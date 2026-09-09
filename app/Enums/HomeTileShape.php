<?php

namespace App\Enums;

/**
 * The shape a tile is drawn in on the guest home screen.
 *
 * One case for now, deliberately. The shape is a column rather than something
 * the front end assumes, so adding a square or a wide banner later is a case
 * here and a class in aspectRatio() — not a migration, and not a rewrite of
 * the home screen.
 */
enum HomeTileShape: string
{
    case Rectangle = 'rectangle';

    public function label(): string
    {
        return match ($this) {
            self::Rectangle => 'Rectangle',
        };
    }

    /**
     * The CSS aspect ratio the guest app draws this shape at.
     *
     * Sent to React rather than hardcoded there, so the shape stored is the
     * shape rendered and there is no second list to keep in step.
     */
    public function aspectRatio(): string
    {
        return match ($this) {
            self::Rectangle => '16 / 9',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $shape): array {
                $options[$shape->value] = $shape->label();

                return $options;
            },
            [],
        );
    }
}
