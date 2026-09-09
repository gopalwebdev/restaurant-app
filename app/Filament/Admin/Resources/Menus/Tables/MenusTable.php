<?php

namespace App\Filament\Admin\Resources\Menus\Tables;

use App\Enums\Locale;
use App\Filament\Admin\Resources\Menus\Schemas\MenuForm;
use App\Filament\Schemas\TranslatedFields;
use App\Models\Menu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenusTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->icon(Heroicon::OutlinedBookOpen)
                    // A translated column holds a JSON document, so both
                    // searching and sorting have to name the language they mean
                    // or they would be matching and ordering whole documents.
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'name', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'name', $direction)),

                TextColumn::make('name_ta')
                    ->label(Locale::Tamil->fieldLabel('Name'))
                    ->state(fn (Menu $record): ?string => $record->getTranslation('name', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder('Not translated')
                    ->toggleable(),

                TextColumn::make('menu_categories_count')
                    ->label('Sections')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->counts('menuCategories')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Showing')
                    ->boolean()
                    ->sortable()
                    ->tooltip('A hidden menu takes its sections and their dishes off the storefront too.'),

                TextColumn::make('position')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Showing'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    // Spatie hands back one language for a translated column;
                    // this form edits them all, so the whole document is put
                    // back before the fields are filled.
                    ->mutateRecordDataUsing(fn (array $data, Menu $record): array => MenuForm::fillTranslations($data, $record)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    // Deleting a menu takes its sections, their dishes and
                    // those dishes' additions with it, by the cascades on the
                    // foreign keys. Say so before it happens.
                    ->modalDescription('Every section on this menu is deleted with it, and every dish in those sections. Hide it instead to take it off the storefront and keep everything.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->defaultSort('position');
    }
}
