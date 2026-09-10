<?php

namespace App\Filament\Admin\Resources\Menus\RelationManagers;

use App\Enums\ItemAvailability;
use App\Filament\Admin\Resources\Menus\Schemas\MenuComboForm;
use App\Filament\Schemas\PricingFields;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
use App\Models\Menu;
use App\Models\MenuCombo;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The bundles this menu offers, in the order a guest reads them.
 *
 * A row of its own beside the featured dishes, and dragged into order the same
 * way — a combo is something a menu leads with rather than something in a
 * section, which is why it hangs off the menu and is arranged here.
 *
 * Unlike the featured row, records really are created and deleted here: a combo
 * is a thing in its own right, not a flag on a dish.
 */
class CombosRelationManager extends RelationManager
{
    protected static string $relationship = 'combos';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedSparkles;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.combos.plural');
    }

    public function form(Schema $schema): Schema
    {
        return MenuComboForm::configure($schema, $this->menu()->getKey());
    }

    public function table(Table $table): Table
    {
        $currency = PricingFields::currency();

        return $table
            ->recordTitleAttribute('name')
            ->heading(__('panel.combos.plural'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.shared.name'))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction))
                    ->description(fn (MenuCombo $record): ?string => $record->description),

                TextColumn::make('combo_items_count')
                    ->label(__('panel.combos.contents'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('comboItems')
                    ->sortable(),

                // Formatted here rather than in the browser: a panel is server
                // rendered, and the currency is resolved once for the page
                // rather than per row. See .ai/rules/models.md.
                TextColumn::make('price_minor_units')
                    ->label(__('panel.items.price'))
                    ->formatStateUsing(fn (MenuCombo $record): string => $record->formattedPrice($currency))
                    // The struck-through price rides under the real one rather
                    // than taking a column of its own, which would be empty for
                    // every combo that is not on offer.
                    ->description(fn (MenuCombo $record): ?string => $record->formattedComparePrice($currency))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('availability')
                    ->label(__('panel.items.availability'))
                    ->badge()
                    ->formatStateUsing(fn (ItemAvailability $state): string => $state->label())
                    ->color(fn (ItemAvailability $state): string => $state->color())
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.combos.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->mutateDataUsing(fn (array $data): array => PricingFields::store($data, $currency)),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuCombo $record): array => MenuComboForm::fillTranslations(
                        PricingFields::fill($data, $currency),
                        $record,
                    ))
                    ->mutateDataUsing(fn (array $data): array => PricingFields::store($data, $currency)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalDescription(__('panel.combos.delete_warning')),
            ])
            ->reorderable('position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position')
            ->emptyStateHeading(__('panel.combos.empty_heading'))
            ->emptyStateDescription(__('panel.combos.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedSparkles);
    }

    private function menu(): Menu
    {
        $menu = $this->getOwnerRecord();

        return $menu instanceof Menu ? $menu : throw new LogicException('The combos relation manager requires a menu.');
    }
}
