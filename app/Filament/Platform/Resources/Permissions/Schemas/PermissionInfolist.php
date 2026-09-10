<?php

namespace App\Filament\Platform\Resources\Permissions\Schemas;

use App\Models\Permission;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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

                TextEntry::make('category')
                    ->label('Category')
                    ->state(fn (Permission $record): string => $record->group()->label())
                    ->icon(fn (Permission $record): Heroicon => $record->group()->icon())
                    ->badge()
                    ->color(fn (Permission $record): string => $record->group()->color())
                    ->helperText(fn (Permission $record): string => $record->group()->description()),

                IconEntry::make('is_built_in')
                    ->label('Built in')
                    ->state(fn (Permission $record): bool => $record->isBuiltIn())
                    ->boolean()
                    ->helperText('Built-in permissions are declared in code, so they are read-only here.'),

                TextEntry::make('undeletable_reason')
                    ->label('Deletable')
                    ->state(fn (Permission $record): string => $record->undeletableReason() ?? 'Yes, no role holds this permission.')
                    ->columnSpanFull(),

                TextEntry::make('roles.name')
                    ->label('Granted to')
                    ->badge()
                    ->placeholder('No roles')
                    ->columnSpanFull(),
            ]);
    }
}
