<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Filament\Admin\Resources\Menus\Schemas\MenuSubCategoryForm;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * The relationship is Menu::subCategories(), a plain hasMany over the rows of
 * menu_categories that carry a parent. Both levels being one table is what
 * makes this simple: it was a HasManyThrough over a separate table before, and
 * that join's ambiguous `id` needed its own reorderTable() override to work
 * around — gone with the join.
 *
 * There is no "move" action here. A sub-category's parent is the first field on
 * its own form, and only this menu's top-level categories are offered there —
 * so re-filing one is an edit, not a second mechanism that has to repeat the
 * same rules. The form's uniqueness rule is scoped to the chosen parent, so
 * changing it revalidates the name against where it is going.
 */
class SubCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'subCategories';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedSquares2x2;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.sub_categories.plural');
    }

    public function form(Schema $schema): Schema
    {
        return MenuSubCategoryForm::configure($schema, $this->menu()->getKey());
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

                TextColumn::make('parent.name')
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
                Group::make('parent_id')
                    ->label(__('panel.categories.section'))
                    // Every row here has a parent, because the table is scoped
                    // to the rows that do — but `parent` is nullable on the
                    // model, so this reads it rather than assuming.
                    ->getTitleFromRecordUsing(function (MenuCategory $record): string {
                        $parent = $record->parent;

                        return $parent instanceof MenuCategory ? $parent->name : $record->name;
                    })
                    // Ordered by the parent's own position via a correlated
                    // subquery, aliased because the table appears twice.
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        MenuCategory::query()
                            ->from('menu_categories as parents')
                            ->select('parents.position')
                            ->whereColumn('parents.id', 'menu_categories.parent_id'),
                        $direction === 'desc' ? 'desc' : 'asc',
                    )),
            ])
            ->defaultGroup('parent_id')
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.sub_categories.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    // A sub-category has to go under a category, so there is
                    // nothing useful to do until this menu has one.
                    ->disabled(fn (): bool => $this->menu()->categories()->doesntExist())
                    ->tooltip(fn (): ?string => $this->menu()->categories()->exists()
                        ? null
                        : $this->text('panel.sub_categories.needs_a_category')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuCategory $record): array => MenuSubCategoryForm::fillTranslations($data, $record)),

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent'));
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

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new LogicException('The sub-categories relation manager requires a menu.');
    }
}
