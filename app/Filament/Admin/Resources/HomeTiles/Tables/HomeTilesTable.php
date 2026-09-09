<?php

namespace App\Filament\Admin\Resources\HomeTiles\Tables;

use App\Enums\HomeTileAction;
use App\Enums\Locale;
use App\Filament\Admin\Resources\HomeTiles\Schemas\HomeTileForm;
use App\Filament\Schemas\TranslatedFields;
use App\Models\HomeTile;
use App\Models\Menu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HomeTilesTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('panel.tiles.picture_column'))
                    ->disk('local')
                    ->visibility('private')
                    ->imageHeight(40)
                    ->defaultImageUrl(null)
                    ->placeholder(__('panel.tiles.no_picture')),

                TextColumn::make('label')
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'label', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'label', $direction)),

                TextColumn::make('label_ta')
                    ->label(Locale::Tamil->fieldLabel(__('panel.tiles.label_field')))
                    ->state(fn (HomeTile $record): ?string => $record->getTranslation('label', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder(__('panel.shared.not_translated'))
                    ->toggleable(),

                TextColumn::make('action')
                    ->label(__('panel.tiles.on_tap'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (HomeTileAction $state): string => $state->label()),

                TextColumn::make('destination')
                    ->label(__('panel.tiles.goes_to'))
                    ->state(fn (HomeTile $record): string => self::destinationOf($record))
                    ->icon(fn (HomeTile $record): Heroicon => match ($record->action) {
                        HomeTileAction::Menu => Heroicon::OutlinedBookOpen,
                        HomeTileAction::Pdf => Heroicon::OutlinedDocumentText,
                    }),

                IconColumn::make('is_active')
                    ->label(__('panel.shared.showing'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('position')
                    ->label(__('panel.shared.order'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('panel.tiles.on_tap'))
                    ->options(HomeTileAction::options()),

                TernaryFilter::make('is_active')->label(__('panel.shared.showing')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, HomeTile $record): array => HomeTileForm::fillTranslations($data, $record)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalDescription(__('panel.tiles.delete_warning')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // Dragging the rows is how a restaurant arranges its home screen,
            // so this is the point of the page rather than a convenience.
            ->reorderable('position')
            ->defaultSort('position')
            ->emptyStateHeading(__('panel.tiles.empty_heading'))
            ->emptyStateDescription(__('panel.tiles.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('menu'));
    }

    /**
     * Where a tile goes, in one phrase whichever kind it is.
     *
     * "Where does this tile go" is one question to a reader, even though it is
     * two columns in the schema. menu_id is nullable because a PDF tile has
     * none, so the menu is checked rather than assumed — the model guard and the
     * cascading foreign key both rule the empty case out, but the column does not.
     */
    private static function destinationOf(HomeTile $tile): string
    {
        if ($tile->action === HomeTileAction::Pdf) {
            return __('panel.tiles.an_uploaded_pdf');
        }

        $menu = $tile->menu;

        return $menu instanceof Menu ? $menu->name : __('panel.tiles.no_menu_chosen');
    }
}
