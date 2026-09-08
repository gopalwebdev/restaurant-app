<?php

namespace App\Filament\SuperAdmin\Resources\Roles\Schemas;

use App\Enums\PermissionGroup;
use App\Models\Permission;
use App\Models\Role;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextEntry::make('name'),

                        IconEntry::make('is_built_in')
                            ->label('Built in')
                            ->state(fn (Role $record): bool => $record->isBuiltIn())
                            ->boolean()
                            ->helperText('Built-in roles are declared in code and seeded, so they are read-only here.'),

                        TextEntry::make('undeletable_reason')
                            ->label('Deletable')
                            ->state(fn (Role $record): string => $record->undeletableReason() ?? 'Yes, nothing depends on this role.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Permissions')
                    ->description('What this role grants, by category.')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema(self::permissionEntries()),
            ]);
    }

    /**
     * One entry per category, so what a role grants reads as areas of the
     * product rather than as a wall of `subject.ability` strings.
     *
     * A category the role grants nothing in is still listed, saying so: the gap
     * is as much a part of what a role is as the badges are.
     *
     * @return list<TextEntry>
     */
    private static function permissionEntries(): array
    {
        return array_map(
            static fn (PermissionGroup $group): TextEntry => TextEntry::make('permissions_'.$group->value)
                ->label($group->label())
                ->icon($group->icon())
                ->badge()
                ->color($group->color())
                ->placeholder('Nothing in this category')
                ->state(fn (Role $record): array => $record->permissions
                    ->filter(fn (Permission $permission): bool => $permission->group() === $group)
                    ->map(fn (Permission $permission): string => $permission->label())
                    ->values()
                    ->all()),
            PermissionGroup::ordered(),
        );
    }
}
