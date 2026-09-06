<?php

namespace App\Filament\SuperAdmin\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),

                IconEntry::make('is_built_in')
                    ->label('Built in')
                    ->state(fn (Role $record): bool => $record->isBuiltIn())
                    ->boolean()
                    ->helperText('Built-in roles are declared in code and seeded, so they are read-only here.'),

                TextEntry::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Permission::query()->where('name', $state)->first()?->label() ?? $state)
                    ->placeholder('No permissions')
                    ->columnSpanFull(),
            ]);
    }
}
