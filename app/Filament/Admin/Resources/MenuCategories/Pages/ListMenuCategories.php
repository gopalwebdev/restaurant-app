<?php

namespace App\Filament\Admin\Resources\MenuCategories\Pages;

use App\Filament\Admin\Resources\MenuCategories\MenuCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMenuCategories extends ListRecords
{
    protected static string $resource = MenuCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('panel.categories.create'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
