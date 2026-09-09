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
                    ->label('Picture')
                    ->disk('local')
                    ->visibility('private')
                    ->imageHeight(40)
                    ->defaultImageUrl(null)
                    ->placeholder('No picture'),

                TextColumn::make('label')
                    ->searchable(query: fn (Builder $query, string $search): Builder => TranslatedFields::search($query, 'label', $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => TranslatedFields::sort($query, 'label', $direction)),

                TextColumn::make('label_ta')
                    ->label(Locale::Tamil->fieldLabel('Label'))
                    ->state(fn (HomeTile $record): ?string => $record->getTranslation('label', Locale::Tamil->value, useFallbackLocale: false) ?: null)
                    ->placeholder('Not translated')
                    ->toggleable(),

                TextColumn::make('action')
                    ->label('On tap')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (HomeTileAction $state): string => $state->label()),

                TextColumn::make('destination')
                    ->label('Goes to')
                    ->state(fn (HomeTile $record): string => self::destinationOf($record))
                    ->icon(fn (HomeTile $record): Heroicon => match ($record->action) {
                        HomeTileAction::Menu => Heroicon::OutlinedBookOpen,
                        HomeTileAction::Pdf => Heroicon::OutlinedDocumentText,
                    }),

                IconColumn::make('is_active')
                    ->label('Showing')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('position')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('On tap')
                    ->options(HomeTileAction::options()),

                TernaryFilter::make('is_active')->label('Showing'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->mutateRecordDataUsing(fn (array $data, HomeTile $record): array => HomeTileForm::fillTranslations($data, $record)),

                DeleteAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->modalDescription('The tile is removed from the home screen. Anything it pointed at — a menu, say — is left alone.'),
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
            ->emptyStateHeading('No tiles yet')
            ->emptyStateDescription('Guests scanning a table\'s QR code see nothing until there is at least one tile here. Most restaurants start with one that opens their menu.')
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
            return 'An uploaded PDF';
        }

        $menu = $tile->menu;

        return $menu instanceof Menu ? $menu->name : 'No menu chosen';
    }
}
