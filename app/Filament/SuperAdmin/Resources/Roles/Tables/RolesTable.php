<?php

namespace App\Filament\SuperAdmin\Resources\Roles\Tables;

use App\Filament\SuperAdmin\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_built_in')
                    ->label('Built in')
                    ->state(fn (Role $record): bool => $record->isBuiltIn())
                    ->boolean()
                    ->tooltip('Built-in roles are declared in code and seeded, so they are read-only.'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->visibleFrom('md'),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                ViewAction::make(),

                // A built-in role is read-only, and the resource is where that
                // is decided. Without asking it here the buttons would render
                // for a super admin and then land on a 403, because Filament
                // authorizes a record action against the policy, which
                // Gate::before answers first.
                EditAction::make()
                    ->visible(fn (Role $record): bool => RoleResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => RoleResource::canDelete($record)),
            ])
            // No bulk delete: the only roles that may be deleted are the custom
            // ones, and a bulk action would have to re-check that per record.
            ->defaultSort('name');
    }
}
