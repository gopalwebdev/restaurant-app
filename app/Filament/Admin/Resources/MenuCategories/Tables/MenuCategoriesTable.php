<?php

namespace App\Filament\Admin\Resources\MenuCategories\Tables;

use App\Enums\Locale;
use App\Filament\Admin\Resources\MenuCategories\Schemas\MenuCategoryForm;
use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use App\Models\MenuCategory;
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

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    // A translated column holds a JSON document, so searching
                    // and sorting have to name the language they mean.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction)),

                TextColumn::make('name_ta')
                    ->label(Locale::Tamil->fieldLabel(__('panel.shared.name')))
                    ->state(fn (MenuCategory $record): ?string => $record->getTranslation('name', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder(__('panel.shared.not_translated'))
                    ->toggleable(),

                TextColumn::make('menu.name')
                    ->label(__('panel.categories.menu'))
                    ->icon(Heroicon::OutlinedBookOpen)
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
                    ->sortable()
                    ->tooltip(__('panel.categories.showing_tooltip')),

                TextColumn::make('position')
                    ->label(__('panel.shared.order'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->groups([
                // Grouped on the foreign key rather than menu.name: that
                // column is translated JSON, and Postgres has no ordering
                // operator for json — grouping or sorting by it 500s there,
                // even though SQLite (what the tests run against) tolerates
                // it. The key, the title and the order all come from the
                // parent explicitly instead. See .ai/rules/filament.md.
                Group::make('menu_id')
                    ->label(__('panel.categories.menu'))
                    ->getTitleFromRecordUsing(fn (MenuCategory $record): string => $record->menu->name)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Menu::query()->select('position')->whereColumn('menus.id', 'menu_categories.menu_id'),
                        $direction,
                    )),
            ])
            ->defaultGroup('menu_id')
            ->filters([
                SelectFilter::make('menu_id')
                    ->label(__('panel.categories.menu'))
                    ->options(fn (): array => MenuCategoryForm::menuOptions()),

                TernaryFilter::make('is_active')->label(__('panel.shared.showing')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, MenuCategory $record): array => MenuCategoryForm::fillTranslations($data, $record)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    // Deleting a section takes its items with it, by the
                    // cascade on the foreign key, so say so before it happens.
                    ->modalDescription(__('panel.categories.delete_warning')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            // The menu each section belongs to is shown as a column and grouped
            // on, so it is loaded once for the page rather than per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menu'));
    }
}
