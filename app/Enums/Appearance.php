<?php

namespace App\Enums;

/**
 * How a restaurant's guest and staff apps are shaded.
 *
 * System follows the phone's own setting, which is the default because a phone
 * in a dark dining room and one on a bright terrace want different answers and
 * neither is the restaurant's to guess.
 */
enum Appearance: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Follow the phone',
            self::Light => 'Always light',
            self::Dark => 'Always dark',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $appearance): array {
                $options[$appearance->value] = $appearance->label();

                return $options;
            },
            [],
        );
    }
}
