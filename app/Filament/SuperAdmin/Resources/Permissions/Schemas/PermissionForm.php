<?php

namespace App\Filament\SuperAdmin\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                    ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*\.[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->helperText('Written as subject.ability, like menu.view. A permission added here does nothing until code checks for it.')
                    ->validationMessages([
                        'regex' => 'Use the form subject.ability, lowercase, with hyphens inside each part.',
                    ]),
            ]);
    }
}
