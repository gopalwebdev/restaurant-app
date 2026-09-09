<?php

namespace App\Filament\Admin\Resources\MenuItems\Tables;

use App\Enums\FoodType;
use App\Enums\Locale;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Schemas\TranslatedFields;
use App\Models\MenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction))
                    ->description(fn (MenuItem $record): ?string => $record->description),

                TextColumn::make('name_ta')
                    ->label(Locale::Tamil->fieldLabel('Name'))
                    ->state(fn (MenuItem $record): ?string => $record->getTranslation('name', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder('Not translated')
                    ->toggleable(),

                TextColumn::make('menuCategory.name')
                    ->label('Section')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('menuCategory.menu.name')
                    ->label('Menu')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

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

                TextColumn::make('additions_count')
                    ->label('Additions')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->counts('additions')
                    ->sortable()
                    ->toggleable(),

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
                Group::make('menuCategory.menu.name')->label('Menu'),
            ])
            ->defaultGroup('menuCategory.name')
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label('Section')
                    ->options(fn (): array => MenuItemForm::sectionOptions()),

                SelectFilter::make('food_type')
                    ->label('Food type')
                    ->options(FoodType::options()),

                TernaryFilter::make('is_available')->label('Available'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuItem $record): array => MenuItemForm::fillTranslations(
                        MenuItemForm::fillPrice($data),
                        $record,
                    ))
                    ->mutateDataUsing(fn (array $data): array => MenuItemForm::storePrice($data)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalDescription('The additions on this dish are deleted with it.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            // The section and its menu are both shown, so they are loaded once
            // for the page rather than per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menuCategory.menu'));
    }
}
