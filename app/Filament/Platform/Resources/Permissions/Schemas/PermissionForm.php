<?php

namespace App\Filament\Platform\Resources\Permissions\Schemas;

use App\Enums\PermissionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Permission names are checked as strings from code, and read
                // as `subject.ability` throughout: menu.view, order.manage.
                TextInput::make('name')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->prefixIcon(Heroicon::OutlinedKey)
                    ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*\.[a-z0-9]+(-[a-z0-9]+)*$/')
                    // The subject half also decides which category the
                    // permission is filed under, so the form says so live
                    // rather than leaving it to be discovered after saving.
                    ->live(debounce: 300)
                    ->helperText(fn (?string $state): string => blank($state)
                        ? 'Written as subject.ability, like menu.view. A permission added here does nothing until code checks for it.'
                        : sprintf(
                            'Filed under %s. A permission added here does nothing until code checks for it.',
                            PermissionGroup::forPermissionName($state)->label(),
                        ))
                    ->validationMessages([
                        'regex' => 'Use the form subject.ability, lowercase, with hyphens inside each part.',
                    ]),
            ]);
    }
}
