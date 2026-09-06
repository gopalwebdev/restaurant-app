<?php

namespace App\Filament\SuperAdmin\Resources\Permissions\Tables;

use App\Filament\SuperAdmin\Resources\Permissions\PermissionResource;
use App\Models\Permission;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Reads as')
                    ->state(fn (Permission $record): string => $record->label()),
                IconColumn::make('is_built_in')
                    ->label('Built in')
                    ->state(fn (Permission $record): bool => $record->isBuiltIn())
                    ->boolean()
                    ->tooltip('Built-in permissions are checked by name from code, so they are read-only.'),
                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles'),
            ])
            ->recordActions([
                ViewAction::make(),

                // As with roles: a built-in permission is read-only, and
                // offering the button would lead a super admin to a 403.
                EditAction::make()
                    ->visible(fn (Permission $record): bool => PermissionResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (Permission $record): bool => PermissionResource::canDelete($record)),
            ])
            // No bulk delete: only custom permissions may be deleted, and that
            // is a per-record question.
            ->defaultSort('name');
    }
}
