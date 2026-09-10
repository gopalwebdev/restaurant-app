<?php

namespace App\Filament\Admin\Resources\MenuItems\Tables;

use App\Enums\FoodType;
use App\Enums\ItemAvailability;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Admin\Resources\Menus\Schemas\MenuCategoryForm;
use App\Filament\Admin\Resources\Menus\Schemas\MenuSubCategoryForm;
use App\Filament\Schemas\PricingFields;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\MenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        $currency = PricingFields::currency();
        // Both resolved once for the page rather than per row: every dish here
        // belongs to the same restaurant and shares its answers.
        $restaurantTaxRate = PricingFields::restaurantTaxRateBasisPoints();

        return $table
            ->columns([
                TextColumn::make('name')
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction))
                    ->description(fn (MenuItem $record): ?string => $record->description),

                TextColumn::make('menuCategory.name')
                    ->label(__('panel.items.section'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    // The branch, not just the leaf: a subdivision on its own
                    // says nothing about which section it belongs to.
                    ->formatStateUsing(fn (MenuItem $record): string => $record->menuCategory->path())
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
                    ->formatStateUsing(fn (MenuItem $record): string => $record->formattedPrice($currency))
                    // The struck-through price rides under the real one rather
                    // than taking a column of its own, which would be empty for
                    // every dish that is not on offer — most of them.
                    ->description(fn (MenuItem $record): ?string => $record->formattedComparePrice($currency))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('tax_rate_basis_points')
                    ->label(__('panel.items.tax_rate'))
                    ->formatStateUsing(fn (MenuItem $record): string => PricingFields::formatRate(
                        $record->taxRateBasisPoints($restaurantTaxRate),
                    ))
                    // A dish following the restaurant's rate is shown in grey
                    // and one that overrides it in colour, so the exceptions
                    // stand out down a long list.
                    ->badge()
                    ->color(fn (MenuItem $record): string => $record->overridesTaxRate() ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('additions_count')
                    ->label(__('panel.additions.count'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->counts('additions')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('availability')
                    ->label(__('panel.items.availability'))
                    ->badge()
                    ->formatStateUsing(fn (ItemAvailability $state): string => $state->label())
                    ->color(fn (ItemAvailability $state): string => $state->color())
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label(__('panel.items.is_featured'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedStar)
                    ->falseIcon(Heroicon::OutlinedMinusSmall)
                    ->sortable(),
            ])
            ->filters([
                // A dish reaches its menu through its category, so this filters
                // on the relationship rather than on a column of its own.
                SelectFilter::make('menu')
                    ->label(__('panel.categories.menu'))
                    ->options(fn (): array => MenuCategoryForm::menuOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $onMenu): Builder => $onMenu->whereRelation(
                            'menuCategory',
                            'menu_id',
                            $data['value'],
                        ),
                    )),

                SelectFilter::make('menu_category_id')
                    ->label(__('panel.items.section'))
                    // Narrowed to the chosen menu when there is one, so picking
                    // a menu and then a category reads as one decision rather
                    // than two lists that repeat each other.
                    ->options(fn (HasTable $livewire): array => self::sectionOptions($livewire)),

                SelectFilter::make('food_type')
                    ->label(__('panel.items.food_type'))
                    ->options(FoodType::options()),

                SelectFilter::make('availability')
                    ->label(__('panel.items.availability'))
                    ->options(ItemAvailability::options()),

                Filter::make('on_offer')
                    ->label(__('panel.items.on_offer'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('compare_at_price_minor_units')),

                TernaryFilter::make('is_featured')->label(__('panel.items.is_featured')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuItem $record): array => MenuItemForm::fillTranslations(
                        MenuItemForm::fillPricing($data),
                        $record,
                    ))
                    ->mutateDataUsing(fn (array $data): array => MenuItemForm::storePricing($data)),

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
            // Dragging is only offered once the table is showing one category,
            // because Filament turns grouping **off** while reordering
            // (CanGroupRecords::getTableGrouping() returns null) and sorts by
            // the reorder column alone. Left ungated, entering drag mode threw
            // away the tree and produced one flat list of every dish on every
            // menu — where dropping a dish between two others rewrote a
            // position that is only ever read within its own category, so the
            // row sprang back on the next load. There is no grouped drag mode
            // to switch on; narrowing the table is the whole fix.
            //
            // isReorderable() is `column && condition && authorized`, so this
            // narrows when dragging is offered without touching who may do it —
            // the reorder() policy check is the separate third term. It also
            // guards the write: reorderTable() short-circuits on the same call,
            // so a request that arrives without the filter set does nothing.
            ->reorderable('position', condition: fn (HasTable $livewire): bool => self::filteredSectionKey($livewire) !== null)
            ->reorderRecordsTriggerAction(Reordering::trigger())
            // Menu, then section, then the order the restaurant dragged the
            // dishes into. Filament's grouping used to imply this; with the
            // group gone the query has to say it.
            ->defaultSort(fn (Builder $query): Builder => self::inMenuOrder($query))
            // The category, its parent and its menu are all read per row for
            // the tree headings, so all three are loaded once for the page.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['menuCategory.menu', 'menuCategory.parent']));
    }

    /**
     * The categories the section filter offers.
     *
     * Every category in the restaurant, at both levels, unless a menu has been
     * picked — then only that menu's, because offering the rest would be
     * offering rows the menu filter has already excluded.
     *
     * @return array<int, string>
     */
    private static function sectionOptions(HasTable $livewire): array
    {
        $menuKey = $livewire->getTableFilterState('menu')['value'] ?? null;

        return filled($menuKey)
            ? MenuSubCategoryForm::categoryOptionsOnMenu((int) $menuKey)
            : MenuItemForm::sectionOptions();
    }

    /**
     * Read the list the way a guest reads the menu.
     *
     * Menu, then the section a dish sits under, then a section's own dishes
     * before its subdivisions', then the order they were dragged into. Grouping
     * used to imply most of this; with the group gone the query says it.
     *
     * Raw because each rank is a correlated subquery over menu_categories,
     * which appears twice — once as the dish's own category and once as that
     * category's parent. Every rank is COALESCEd rather than left null:
     * Postgres sorts nulls last ascending and SQLite sorts them first, so a
     * null would put the sections at opposite ends of the list on the two
     * engines, and the suite runs on SQLite.
     *
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    private static function inMenuOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                '(select menus.position from menus'
                .' join menu_categories on menu_categories.menu_id = menus.id'
                .' where menu_categories.id = menu_items.menu_category_id)'
            )
            ->orderByRaw(
                'coalesce('
                .'(select parents.position from menu_categories parents'
                .' join menu_categories own on own.parent_id = parents.id'
                .' where own.id = menu_items.menu_category_id), '
                .'(select position from menu_categories where id = menu_items.menu_category_id)'
                .')'
            )
            ->orderByRaw(
                'coalesce((select case when parent_id is null then -1 else position end'
                .' from menu_categories where id = menu_items.menu_category_id), -1)'
            )
            ->orderBy('position');
    }

    /**
     * The category the table is currently narrowed to, if it is narrowed to one.
     *
     * Dishes are ordered within their own category, so this is what says whether
     * dragging can mean anything on screen right now.
     */
    private static function filteredSectionKey(HasTable $livewire): ?int
    {
        $value = $livewire->getTableFilterState('menu_category_id')['value'] ?? null;

        return filled($value) ? (int) $value : null;
    }
}
