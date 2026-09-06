<?php

namespace App\Filament\SuperAdmin\Resources\Restaurants\Tables;

use App\Models\Restaurant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RestaurantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Subdomain')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Mobile')
                    ->state(fn (Restaurant $record): string => $record->dialablePhone())
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('secondary_phone')
                    ->label('Secondary mobile')
                    ->state(fn (Restaurant $record): ?string => $record->dialableSecondaryPhone())
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pincode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Open')
                    ->boolean(),
                TextColumn::make('users_count')
                    ->label('Staff')
                    ->counts('users'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Open for business'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
