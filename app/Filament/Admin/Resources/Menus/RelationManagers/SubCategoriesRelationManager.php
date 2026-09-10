<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Actions\Menus\MoveSubCategoryToCategory;
use App\Enums\Locale;
use App\Filament\Admin\Resources\Menus\Schemas\MenuSubCategoryForm;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuSubCategory;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The subdivisions of this menu's categories, grouped under the category each
 * one belongs to.
 *
 * Grouping is what makes this read as the second level of a tree rather than a
 * flat list: the category is the heading, its sub-categories are the rows under
 * it, and dragging reorders them within their own category.
 *
 * The relationship is Menu::menuSubCategories(), a HasManyThrough, because a
 * sub-category reaches its menu through its category and has no menu column of
 * its own. Filament saves a record created against a through-relationship
 * directly rather than via the relation — which is exactly right here, since
 * the form names the category and the composite foreign key does the rest.
 *
 * Moving one to another category is an action rather than a select on the form,
 * matching how a category moves between menus. It is deliberately limited to
 * the categories of this menu — see MoveSubCategoryToCategory.
 */
class SubCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'menuSubCategories';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedSquares2x2;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.sub_categories.plural');
    }

    public function form(Schema $schema): Schema
    {
        return MenuSubCategoryForm::configure($schema, $this->menu()->getKey());
    }

    /**
     * Reorder against the table itself rather than through the relationship.
     *
     * Filament reorders by running one UPDATE over `$table->getQuery()`, keyed
     * on the model's unqualified `id`. That query here is a HasManyThrough, so
     * it carries a join to menu_categories — and both the `where in (id, ...)`
     * and the `case when id = ...` it builds become "ambiguous column name: id"
     * on SQLite and Postgres alike. Dragging a sub-category would 500.
     *
     * So the same update is issued against menu_sub_categories on its own, with
     * the menu named as a plain subquery instead of a join. The reorderable
     * check stays first and stays exactly what it was: it is the reorder()
     * policy method, and it is the only thing keeping the drag away from
     * someone who may only read the menu (.ai/rules/tables.md).
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $this->getTable()->callBeforeReordering($order);

        $connection = MenuSubCategory::query()->getModel()->getConnection();

        MenuSubCategory::query()
            ->whereIn('menu_sub_categories.id', array_values($order))
            // The tenant boundary the joined query gave for free, restated:
            // only the sub-categories of this menu's own categories move.
            ->whereIn('menu_category_id', MenuCategory::query()
                ->select('id')
                ->where('menu_id', $this->menu()->getKey()))
            ->update([
                'position' => $this->makeTableReorderColumnExpression(
                    $order,
                    $connection->getQueryGrammar()->wrap('menu_sub_categories.id'),
                    $connection,
                ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(__('panel.sub_categories.plural'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.shared.name'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction)),

                TextColumn::make('menuCategory.name')
                    ->label(__('panel.categories.section'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('menu_items_count')
                    ->label(__('panel.categories.items_count'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('menuItems')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.shared.showing'))
                    ->boolean()
                    ->sortable(),

            ])
            ->groups([
                // Grouped on the foreign key rather than menuCategory.name:
                // that column is translated JSON, and Postgres has no ordering
                // operator for json — grouping or sorting by it 500s there even
                // though SQLite tolerates it. The key, the title and the order
                // all come from the parent explicitly instead. See
                // .ai/rules/tables.md.
                Group::make('menu_category_id')
                    ->label(__('panel.categories.section'))
                    ->getTitleFromRecordUsing(fn (MenuSubCategory $record): string => $record->menuCategory->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        MenuCategory::query()
                            ->select('position')
                            ->whereColumn('menu_categories.id', 'menu_sub_categories.menu_category_id'),
                        $direction === 'desc' ? 'desc' : 'asc',
                    )),
            ])
            ->defaultGroup('menu_category_id')
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.sub_categories.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    // A sub-category has to go under a category, so there is
                    // nothing useful to do until this menu has one.
                    ->disabled(fn (): bool => $this->menu()->menuCategories()->doesntExist())
                    ->tooltip(fn (): ?string => $this->menu()->menuCategories()->exists()
                        ? null
                        : self::text('panel.sub_categories.needs_a_category')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuSubCategory $record): array => MenuSubCategoryForm::fillTranslations($data, $record)),

                Action::make('moveToCategory')
                    ->label(__('panel.sub_categories.move'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('gray')
                    ->authorize('update')
                    ->modalHeading(__('panel.sub_categories.move'))
                    ->modalDescription(__('panel.sub_categories.move_help'))
                    ->schema([
                        Select::make('menu_category_id')
                            ->label(__('panel.sub_categories.move_target'))
                            ->options(fn (MenuSubCategory $record): array => $this->otherCategories($record))
                            ->required()
                            ->native(false)
                            ->prefixIcon(Heroicon::OutlinedRectangleStack)
                            // Uniqueness is per category and built on the
                            // English name, so the clash is caught here rather
                            // than at the expression index.
                            ->rule(fn (MenuSubCategory $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                $taken = MenuSubCategory::query()
                                    ->where('menu_category_id', $value)
                                    ->where('name->'.Locale::default()->value, $record->getTranslation('name', Locale::default()->value))
                                    ->exists();

                                if ($taken) {
                                    $fail(__('panel.sub_categories.unique'));
                                }
                            })
                            ->helperText(fn (MenuSubCategory $record): ?string => $this->otherCategories($record) === []
                                ? self::text('panel.sub_categories.move_none')
                                : null),
                    ])
                    ->action(function (MenuSubCategory $record, array $data): void {
                        $target = MenuCategory::query()->whereKey($data['menu_category_id'])->firstOrFail();

                        app(MoveSubCategoryToCategory::class)($record, $target);

                        Notification::make()
                            ->title(__('panel.sub_categories.moved'))
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalDescription(__('panel.sub_categories.delete_warning')),
            ])
            ->reorderable('position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position')
            ->emptyStateHeading(__('panel.sub_categories.empty_heading'))
            ->emptyStateDescription(__('panel.sub_categories.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            // The category each one belongs to is a column and the grouping, so
            // it is loaded once for the page rather than per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menuCategory'));
    }

    /**
     * A translation that is definitely a string.
     *
     * `__()` is typed as string|array|null because a key may hold either, so
     * the call sites with a declared return type check rather than cast.
     */
    private static function text(string $key): ?string
    {
        $text = __($key);

        return is_string($text) ? $text : null;
    }

    /**
     * The categories of this menu, minus the one it already sits under.
     *
     * Only this menu's: a sub-category never changes menus, which is what
     * MoveSubCategoryToCategory enforces and this offers.
     *
     * @return array<int, string>
     */
    private function otherCategories(MenuSubCategory $record): array
    {
        return array_diff_key(
            MenuSubCategoryForm::categoryOptions($this->menu()->getKey()),
            [$record->menu_category_id => null],
        );
    }

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new LogicException('The sub-categories relation manager requires a menu.');
    }
}
