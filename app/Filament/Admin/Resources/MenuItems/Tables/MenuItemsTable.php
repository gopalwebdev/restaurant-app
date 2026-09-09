<?php

namespace App\Filament\Admin\Resources\MenuItems\Tables;

use App\Actions\Menus\MoveItemToSection;
use App\Enums\FoodType;
use App\Enums\ItemAvailability;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Admin\Resources\Menus\Schemas\MenuSubCategoryForm;
use App\Filament\Schemas\PricingFields;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuSubCategory;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsTable
{
    /**
     * What separates the two halves of a composite group key, and of the
     * heading built from them.
     *
     * A colon cannot appear in an id, so splitting a key is unambiguous.
     */
    private const string KEY_SEPARATOR = ':';

    private const string TITLE_SEPARATOR = ' › ';

    public static function configure(Table $table): Table
    {
        $currency = PricingFields::currency();

        return $table
            ->columns([
                TextColumn::make('name')
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction))
                    ->description(fn (MenuItem $record): ?string => $record->description),

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
                    ->description(fn (MenuItem $record): ?string => $record->formattedStrikePrice($currency))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('tax_rate_basis_points')
                    ->label(__('panel.items.tax_rate'))
                    ->formatStateUsing(fn (MenuItem $record): string => $record->taxRate()->label())
                    // A dish following the restaurant's rate is shown in grey
                    // and one that overrides it in colour, so the exceptions
                    // stand out down a long list.
                    ->badge()
                    ->color(fn (MenuItem $record): string => $record->tax_rate_basis_points === null ? 'gray' : 'info')
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
                    ->sortable()
                    ->tooltip(__('panel.items.is_featured_help')),
            ])
            ->groups([
                // The tree. One group per place a dish can sit — a category, or
                // one of its sub-categories — so the heading reads
                // "Biryani › Chicken" and the dishes filed straight under
                // Biryani get a group of their own above them.
                //
                // The key has to be composite because neither column answers on
                // its own: grouping by menu_sub_category_id alone would sweep
                // every un-subdivided dish in the restaurant into one null
                // group, and grouping by menu_category_id alone would flatten
                // the subdivisions back out. Every piece Filament needs — the
                // key, the title, the ordering and the way a key is turned back
                // into a query — is therefore supplied explicitly. The column
                // name passed to make() is only an identifier here.
                Group::make('section')
                    ->label(__('panel.items.section'))
                    ->getKeyFromRecordUsing(fn (MenuItem $record): string => self::sectionKey(
                        $record->menu_category_id,
                        $record->menu_sub_category_id,
                    ))
                    ->getTitleFromRecordUsing(fn (MenuItem $record): string => self::sectionTitle($record))
                    ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key): Builder => self::scopeToSection($query, $key))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => self::orderBySection($query, $direction)),

                // Grouped on the foreign key rather than the translated name
                // column: those hold JSON, and Postgres has no ordering
                // operator for json — grouping or sorting by one 500s there,
                // even though SQLite (what the tests run against) tolerates it.
                // See .ai/rules/tables.md.
                Group::make('menuCategory.menu_id')
                    ->label(__('panel.categories.menu'))
                    ->getTitleFromRecordUsing(fn (MenuItem $record): string => $record->menuCategory->menu->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Menu::query()
                            ->select('menus.position')
                            ->join('menu_categories', 'menu_categories.menu_id', '=', 'menus.id')
                            ->whereColumn('menu_categories.id', 'menu_items.menu_category_id'),
                        self::direction($direction),
                    )),
            ])
            ->defaultGroup('section')
            ->filters([
                SelectFilter::make('menu_category_id')
                    ->label(__('panel.items.section'))
                    ->options(fn (): array => MenuItemForm::sectionOptions()),

                SelectFilter::make('food_type')
                    ->label(__('panel.items.food_type'))
                    ->options(FoodType::options()),

                SelectFilter::make('availability')
                    ->label(__('panel.items.availability'))
                    ->options(ItemAvailability::options()),

                Filter::make('discounted')
                    ->label(__('panel.items.on_offer'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('strike_price_minor_units')),

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

                // Refiling a dish is its own action rather than the two selects
                // on the edit form, so moving twenty dishes into a new
                // sub-category does not mean opening twenty full forms.
                Action::make('moveToSection')
                    ->label(__('panel.items.move'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('gray')
                    ->authorize('update')
                    ->modalHeading(__('panel.items.move'))
                    ->modalDescription(__('panel.items.move_help'))
                    ->fillForm(fn (MenuItem $record): array => [
                        'menu_category_id' => $record->menu_category_id,
                        'menu_sub_category_id' => $record->menu_sub_category_id,
                    ])
                    ->schema([
                        Select::make('menu_category_id')
                            ->label(__('panel.items.section'))
                            ->options(fn (): array => MenuItemForm::sectionOptions())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('menu_sub_category_id', null))
                            ->prefixIcon(Heroicon::OutlinedRectangleStack),

                        Select::make('menu_sub_category_id')
                            ->label(__('panel.sub_categories.label'))
                            ->options(fn (Get $get): array => MenuSubCategoryForm::subCategoryOptions($get('menu_category_id')))
                            ->searchable()
                            ->preload()
                            ->prefixIcon(Heroicon::OutlinedSquares2x2)
                            ->visible(fn (Get $get): bool => MenuSubCategoryForm::subCategoryOptions($get('menu_category_id')) !== [])
                            ->placeholder(__('panel.items.no_sub_category'))
                            ->helperText(__('panel.items.move_sub_category_help')),
                    ])
                    ->action(function (MenuItem $record, array $data): void {
                        // firstOrFail() rather than findOrFail(), which is
                        // typed as returning a model *or* a collection because
                        // it also accepts an array of keys.
                        $category = MenuCategory::query()->whereKey($data['menu_category_id'])->firstOrFail();

                        $subCategory = blank($data['menu_sub_category_id'] ?? null)
                            ? null
                            : MenuSubCategory::query()->whereKey($data['menu_sub_category_id'])->firstOrFail();

                        app(MoveItemToSection::class)($record, $category, $subCategory);

                        Notification::make()
                            ->title(__('panel.items.moved'))
                            ->success()
                            ->send();
                    }),

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
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position')
            // The category, its menu and the sub-category are all read per row
            // for the tree headings, so all three are loaded once for the page.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['menuCategory.menu', 'menuSubCategory']));
    }

    /**
     * Filament's sort direction, narrowed to the two values it ever sends.
     *
     * It arrives as a plain string and is interpolated into raw SQL below, so
     * this is where it stops being anything else.
     *
     * @return 'asc'|'desc'
     */
    private static function direction(string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * The group key for one place on the menu.
     *
     * "12:" is the dishes filed straight under category 12, "12:34" the ones in
     * its sub-category 34.
     */
    private static function sectionKey(int $categoryId, ?int $subCategoryId): string
    {
        return $categoryId.self::KEY_SEPARATOR.$subCategoryId;
    }

    /**
     * The heading over one group: "Biryani", or "Biryani › Chicken".
     */
    private static function sectionTitle(MenuItem $record): string
    {
        $category = $record->menuCategory->name;
        $subCategory = $record->menuSubCategory?->name;

        return $subCategory === null
            ? $category
            : $category.self::TITLE_SEPARATOR.$subCategory;
    }

    /**
     * Narrow a query to one group, from the key above.
     *
     * Filament asks for this when it collapses a group or summarises one, so
     * the key has to survive the round trip back into SQL. An empty
     * sub-category half means "filed straight under the category", which is a
     * null check rather than a comparison — `where(x, null)` matches nothing.
     *
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    private static function scopeToSection(Builder $query, ?string $key): Builder
    {
        if ($key === null) {
            return $query;
        }

        [$categoryId, $subCategoryId] = array_pad(explode(self::KEY_SEPARATOR, $key, 2), 2, '');

        return $query
            ->where('menu_category_id', $categoryId)
            ->when(
                $subCategoryId === '',
                fn (Builder $withoutSubCategory): Builder => $withoutSubCategory->whereNull('menu_sub_category_id'),
                fn (Builder $withSubCategory): Builder => $withSubCategory->where('menu_sub_category_id', $subCategoryId),
            );
    }

    /**
     * Order the groups the way the restaurant arranged the menu.
     *
     * Category first, then sub-category, so a category's own dishes head its
     * branch and its subdivisions follow in their dragged order.
     *
     * The sub-category rank is coalesced to -1 rather than left null on
     * purpose: Postgres sorts nulls last ascending and SQLite sorts them first,
     * so a null would put the un-subdivided dishes at opposite ends of the
     * branch on the two engines — and the test suite, which runs on SQLite,
     * would never see it.
     *
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    private static function orderBySection(Builder $query, string $direction): Builder
    {
        $direction = self::direction($direction);

        return $query
            ->orderBy(
                MenuCategory::query()
                    ->select('position')
                    ->whereColumn('menu_categories.id', 'menu_items.menu_category_id'),
                $direction,
            )
            ->orderByRaw(
                'coalesce((select position from menu_sub_categories where menu_sub_categories.id = menu_items.menu_sub_category_id), -1) '.$direction,
            );
    }
}
