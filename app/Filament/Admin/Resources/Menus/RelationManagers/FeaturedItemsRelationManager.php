<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Enums\FoodType;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The dishes this menu leads with, in the order a guest reads them.
 *
 * Featuring is a flag on the dish rather than a table of its own, so nothing is
 * created, deleted or featured here — this row exists to be **put in order**,
 * which is the one thing a dish's own form cannot do. Whether a dish is
 * featured at all is the `is_featured` toggle on that form; having a second
 * pair of actions here to set the same flag was two mechanisms for one thing.
 *
 * The relationship is Menu::menuItems(), which reaches dishes through their
 * sections because that is the only path there is; the featured filter is
 * applied to the table's own query.
 */
class FeaturedItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'menuItems';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedStar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.items.featured_heading');
    }

    /**
     * Reorder against menu_items itself rather than through the relationship.
     *
     * Filament reorders by running one UPDATE over `$table->getQuery()`, keyed
     * on the model's unqualified `id`. This table's query is Menu::menuItems(),
     * a HasManyThrough, so it carries a join to menu_categories — and both the
     * `where in (id, ...)` and the `case when id = ...` it builds resolve to
     * "ambiguous column name: id" on SQLite and Postgres alike. Dragging the
     * featured row 500s without this.
     *
     * So the same update is issued against menu_items on its own, with the menu
     * named as a plain subquery instead of a join. The reorderable check stays
     * first and stays exactly what it was: it is the reorder() policy method,
     * and it is the only thing keeping the drag away from someone who may only
     * read the menu (.ai/rules/tables.md).
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $this->getTable()->callBeforeReordering($order);

        $connection = MenuItem::query()->getModel()->getConnection();

        MenuItem::query()
            ->whereIn('menu_items.id', array_values($order))
            // The tenant boundary the joined query gave for free, restated:
            // only dishes in this menu's own categories move.
            ->whereIn('menu_category_id', MenuCategory::query()
                ->select('id')
                ->where('menu_id', $this->menu()->getKey()))
            ->update([
                'featured_position' => $this->makeTableReorderColumnExpression(
                    $order,
                    $connection->getQueryGrammar()->wrap('menu_items.id'),
                    $connection,
                ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(__('panel.items.featured_heading'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.shared.name'))
                    ->description(fn (MenuItem $record): ?string => $record->description),

                TextColumn::make('menuCategory.name')
                    ->label(__('panel.items.section'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('food_type')
                    ->label(__('panel.items.type'))
                    ->badge()
                    ->formatStateUsing(fn (FoodType $state): string => $state->label())
                    ->color(fn (FoodType $state): string => $state->color()),

                TextColumn::make('price_minor_units')
                    ->label(__('panel.items.price'))
                    ->formatStateUsing(fn (MenuItem $record): string => $record->formattedPrice(MenuItemForm::currency()))
                    ->alignEnd(),
            ])
            ->reorderable('featured_position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('featured_position')
            ->emptyStateHeading(__('panel.items.featured_empty_heading'))
            // The empty state is the one place that has to say where featuring
            // happens, because there is no button here to do it with.
            ->emptyStateDescription(__('panel.items.featured_empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedStar)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('is_featured', true)
                ->with('menuCategory'));
    }

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new \LogicException('The featured dishes relation manager requires a menu.');
    }
}
