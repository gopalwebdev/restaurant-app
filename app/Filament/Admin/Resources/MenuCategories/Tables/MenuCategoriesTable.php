<?php

namespace App\Filament\Admin\Resources\MenuCategories\Tables;

use App\Enums\Locale;
use App\Filament\Admin\Resources\MenuCategories\Schemas\MenuCategoryForm;
use App\Filament\Schemas\TranslatedFields;
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
                    ->label(Locale::Tamil->fieldLabel('Name'))
                    ->state(fn (MenuCategory $record): ?string => $record->getTranslation('name', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder('Not translated')
                    ->toggleable(),

                TextColumn::make('menu.name')
                    ->label('Menu')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('menu_items_count')
                    ->label('Items')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->counts('menuItems')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Showing')
                    ->boolean()
                    ->sortable()
                    ->tooltip('A hidden section takes everything in it off the menu too.'),

                TextColumn::make('position')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),
            ])
            ->groups([
                Group::make('menu.name')->label('Menu'),
            ])
            ->defaultGroup('menu.name')
            ->filters([
                SelectFilter::make('menu_id')
                    ->label('Menu')
                    ->options(fn (): array => MenuCategoryForm::menuOptions()),

                TernaryFilter::make('is_active')->label('Showing'),
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
                    ->modalDescription('Everything on this section of the menu is deleted with it. Hide it instead to take it off the menu and keep the items.'),
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
