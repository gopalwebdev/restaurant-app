<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Enums\FoodType;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
 * created or deleted here — a dish is added to the row or taken out of it, and
 * either way it stays on the menu under its own section. That is why this has
 * no create action and no delete: both would mean something else entirely.
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(__('panel.items.featured_heading'))
            ->description(__('panel.items.featured_description'))
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
            ->headerActions([
                Action::make('feature')
                    ->label(__('panel.items.featured_add'))
                    ->icon(Heroicon::OutlinedStar)
                    ->schema([
                        Select::make('menu_item_id')
                            ->label(__('panel.items.featured_pick'))
                            ->options(fn (): array => $this->featurableItems())
                            ->searchable()
                            ->required()
                            ->helperText(__('panel.items.featured_pick_help')),
                    ])
                    ->action(function (array $data): void {
                        // Scoped to this menu's own dishes, whatever the form
                        // submits: the options are built from the menu, and so
                        // is the update that follows.
                        $this->menu()->menuItems()
                            ->whereKey($data['menu_item_id'])
                            ->update([
                                'is_featured' => true,
                                'featured_position' => $this->nextFeaturedPosition(),
                            ]);
                    }),
            ])
            ->recordActions([
                Action::make('unfeature')
                    ->label(__('panel.items.featured_remove'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('panel.items.featured_remove_warning'))
                    ->action(fn (MenuItem $record) => $record->update(['is_featured' => false])),
            ])
            ->reorderable('featured_position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('featured_position')
            ->emptyStateHeading(__('panel.items.featured_empty_heading'))
            ->emptyStateDescription(__('panel.items.featured_empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedStar)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('is_featured', true)
                ->with('menuCategory'));
    }

    /**
     * The dishes on this menu that are not in the featured row yet.
     *
     * @return array<int, string>
     */
    private function featurableItems(): array
    {
        return $this->menu()->menuItems()
            ->where('is_featured', false)
            ->get()
            ->mapWithKeys(fn (MenuItem $item): array => [$item->getKey() => $item->name])
            ->all();
    }

    /**
     * Put a newly featured dish at the end of the row rather than the front.
     */
    private function nextFeaturedPosition(): int
    {
        return (int) $this->menu()->menuItems()
            ->where('is_featured', true)
            ->max('featured_position') + 1;
    }

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new \LogicException('The featured dishes relation manager requires a menu.');
    }
}
