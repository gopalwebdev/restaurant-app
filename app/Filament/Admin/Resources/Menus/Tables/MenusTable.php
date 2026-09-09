<?php

namespace App\Filament\Admin\Resources\Menus\Tables;

use App\Filament\Admin\Resources\Menus\Schemas\MenuForm;
use App\Filament\Schemas\TranslatedFields;
use App\Filament\Tables\Reordering;
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

                TextColumn::make('menu_categories_count')
                    ->label(__('panel.menus.sections_count'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->counts('menuCategories')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('panel.shared.showing'))
                    ->boolean()
                    ->sortable()
                    ->tooltip(__('panel.menus.showing_tooltip')),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('panel.shared.showing')),
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
                    ->modalDescription(__('panel.menus.delete_warning')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->reorderRecordsTriggerAction(Reordering::trigger())
            ->defaultSort('position');
    }
}
