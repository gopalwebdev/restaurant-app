<?php

namespace App\Filament\Admin\Resources\MenuCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('menu_items_count')
                    ->label('Items')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('menuItems')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Showing')
                    ->boolean()
                    ->sortable()
                    ->tooltip('A hidden section takes everything in it off the menu too.'),

                TextColumn::make('position')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Showing'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare),
                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    // Deleting a section takes its items with it, by the
                    // cascade on the foreign key, so say so before it happens.
                    ->modalDescription('Everything on this section of the menu is deleted with it. Hide it instead to take it off the menu and keep the items.'),
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
