<?php

namespace App\Filament\Admin\Resources\MenuItems\Tables;

use App\Enums\FoodType;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (MenuItem $record): ?string => $record->description),

                TextColumn::make('menuCategory.name')
                    ->label('Section')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('food_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (FoodType $state): string => $state->label())
                    ->color(fn (FoodType $state): string => $state->color()),

                // Stored in minor units, shown as money in the restaurant's own
                // currency. Sorting works on the integer, which is the point of
                // storing it that way.
                TextColumn::make('price_minor_units')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state): string => MenuItemForm::currency()->format($state))
                    ->sortable()
                    ->alignEnd(),

                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('position')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('menuCategory.name')->label('Section'),
            ])
            ->defaultGroup('menuCategory.name')
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label('Section')
                    ->options(fn (): array => MenuCategory::query()
                        ->where('restaurant_id', Filament::getTenant()?->getKey())
                        ->inMenuOrder()
                        ->pluck('name', 'id')
                        ->all()),

                SelectFilter::make('food_type')
                    ->label('Food type')
                    ->options(FoodType::options()),

                TernaryFilter::make('is_available')->label('Available'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data): array => MenuItemForm::fillPrice($data))
                    ->mutateDataUsing(fn (array $data): array => MenuItemForm::storePrice($data)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->defaultSort('position');
    }
}
