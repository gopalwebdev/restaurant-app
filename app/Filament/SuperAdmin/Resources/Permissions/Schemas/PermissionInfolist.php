<?php

namespace App\Filament\SuperAdmin\Resources\Permissions\Schemas;

use App\Models\Permission;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PermissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),

                TextEntry::make('label')
                    ->label('Reads as')
                    ->state(fn (Permission $record): string => $record->label()),

                IconEntry::make('is_built_in')
                    ->label('Built in')
                    ->state(fn (Permission $record): bool => $record->isBuiltIn())
                    ->boolean()
                    ->helperText('Built-in permissions are declared in code, so they are read-only here.'),

                TextEntry::make('roles.name')
                    ->label('Granted to')
                    ->badge()
                    ->placeholder('No roles')
                    ->columnSpanFull(),
            ]);
    }
}
