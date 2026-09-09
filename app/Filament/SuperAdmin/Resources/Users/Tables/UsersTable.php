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
use Illuminate\Database\Eloquent\Builder;

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
                    ->label('Restaurant')
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
                // Where an account belongs, which is the tenant column alone —
                // not what it may do. An ordinary account waiting to be put on
                // a roster has no restaurant either, so this reads "belongs to
                // the platform", and the product team filter below is the
                // separate question of what someone holds.
                SelectFilter::make('belongs_to')
                    ->label('Belongs to')
                    ->options([
                        'restaurant' => 'A restaurant',
                        'product_team' => 'The product team',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'restaurant' => $query->whereNotNull('tenant_id'),
                        'product_team' => $query->whereNull('tenant_id'),
                        default => $query,
                    }),

                SelectFilter::make('tenant_id')
                    ->label('Restaurant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_super_admin')
                    ->label('Holds product team access'),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
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
