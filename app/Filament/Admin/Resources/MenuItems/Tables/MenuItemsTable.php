<?php

namespace App\Filament\Admin\Resources\MenuItems\Tables;

use App\Enums\FoodType;
use App\Enums\Locale;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use App\Models\MenuCategory;
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
                    ->label(Locale::Tamil->fieldLabel(__('panel.shared.name')))
                    ->state(fn (MenuItem $record): ?string => $record->getTranslation('name', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder(__('panel.shared.not_translated'))
                    ->toggleable(),

                TextColumn::make('menuCategory.name')
                    ->label(__('panel.items.section'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('menuCategory.menu.name')
                    ->label(__('panel.categories.menu'))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('food_type')
                    ->label(__('panel.items.type'))
                    ->badge()
                    ->formatStateUsing(fn (FoodType $state): string => $state->label())
                    ->color(fn (FoodType $state): string => $state->color()),

                // Stored in minor units, shown as money in the restaurant's own
                // currency. Sorting works on the integer, which is the point of
                // storing it that way.
                //
                // Formatted here rather than in the browser, unlike the guest
                // and staff apps: a panel is server rendered, and the currency
                // is resolved once for the page rather than per row.
                TextColumn::make('price_minor_units')
                    ->label(__('panel.items.price'))
                    ->formatStateUsing(fn (MenuItem $record): string => $record->formattedPrice(MenuItemForm::currency()))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('additions_count')
                    ->label(__('panel.additions.count'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->counts('additions')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_available')
                    ->label(__('panel.items.is_available'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label(__('panel.items.is_featured'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)
                    ->falseIcon(Heroicon::OutlinedMinusSmall)
                    ->sortable()
                    ->tooltip(__('panel.items.is_featured_help')),

                TextColumn::make('position')
                    ->label(__('panel.shared.order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                // Grouped on foreign keys rather than the translated name
                // columns: those hold JSON, and Postgres has no ordering
                // operator for json — grouping or sorting by one 500s there,
                // even though SQLite (what the tests run against) tolerates
                // it. Each group states its own key, title and order instead
                // of leaning on the relationship path. See
                // .ai/rules/filament.md.
                Group::make('menu_category_id')
                    ->label(__('panel.items.section'))
                    ->getTitleFromRecordUsing(fn (MenuItem $record): string => $record->menuCategory->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        MenuCategory::query()->select('position')->whereColumn('menu_categories.id', 'menu_items.menu_category_id'),
                        $direction,
                    )),

                Group::make('menuCategory.menu_id')
                    ->label(__('panel.categories.menu'))
                    ->getTitleFromRecordUsing(fn (MenuItem $record): string => $record->menuCategory->menu->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Menu::query()
                            ->select('menus.position')
                            ->join('menu_categories', 'menu_categories.menu_id', '=', 'menus.id')
                            ->whereColumn('menu_categories.id', 'menu_items.menu_category_id'),
                        $direction,
                    )),
            ])
            ->defaultGroup('menu_category_id')
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label(__('panel.items.section'))
                    ->options(fn (): array => MenuItemForm::sectionOptions()),

                SelectFilter::make('food_type')
                    ->label(__('panel.items.food_type'))
                    ->options(FoodType::options()),

                TernaryFilter::make('is_available')->label(__('panel.items.is_available')),

                TernaryFilter::make('is_featured')->label(__('panel.items.is_featured')),
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
                    ->modalDescription(__('panel.items.delete_warning')),
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
