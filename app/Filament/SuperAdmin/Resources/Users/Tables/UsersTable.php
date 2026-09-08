<?php

namespace App\Filament\SuperAdmin\Resources\Users\Tables;

use App\Filament\SuperAdmin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->searchable()
                    ->copyable(),

                // A null tenant is what "Product team" means on this table. It is
                // where the account belongs, not what it may do — the badge
                // beside it answers that.
                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->badge()
                    ->color('gray')
                    ->placeholder('Product team')
                    ->sortable(),

                IconColumn::make('is_super_admin')
                    ->label('The product team')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedShieldCheck)
                    ->falseIcon(Heroicon::OutlinedMinusSmall)
                    ->sortable()
                    ->tooltip('The product team hold every permission on every restaurant.'),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->placeholder('None'),

                TextColumn::make('created_at')
                    ->label('Account created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Restaurant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_super_admin')
                    ->label('The product team'),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare),

                // Hidden against your own row: UserResource::canDelete() is
                // where that is decided, because Gate::before answers the
                // policy for a super admin before it ever runs.
                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->visible(fn (User $record): bool => UserResource::canDelete($record)),
            ])
            // No bulk delete: an account may be the last way into a restaurant,
            // and that is a per-record question.
            ->defaultSort('name');
    }
}
