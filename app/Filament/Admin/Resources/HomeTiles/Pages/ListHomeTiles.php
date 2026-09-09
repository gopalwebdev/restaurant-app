<?php

namespace App\Filament\Admin\Resources\HomeTiles\Pages;

use App\Filament\Admin\Resources\HomeTiles\HomeTileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * The home screen, arranged in the order the rows are dragged into.
 *
 * Tiles are few and arranged against each other, so they are created and
 * edited in modals here rather than on pages of their own — the same shape as
 * ListMenuCategories and ListMenus.
 */
class ListHomeTiles extends ListRecords
{
    protected static string $resource = HomeTileResource::class;

    public function getHeading(): string
    {
        return 'Home screen';
    }

    public function getSubheading(): string
    {
        return 'What a guest sees after scanning the QR code at their table, in the order shown here.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New tile')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
