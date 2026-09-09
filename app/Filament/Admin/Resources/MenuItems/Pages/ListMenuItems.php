<?php

namespace App\Filament\Admin\Resources\MenuItems\Pages;

use App\Filament\Admin\Resources\MenuItems\MenuItemResource;
use App\Filament\Admin\Resources\MenuItems\Schemas\MenuItemForm;
use App\Models\MenuCategory;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New dish')
                ->icon(Heroicon::OutlinedPlus)
                // A dish has to go in a section, and a section on a menu, so
                // there is nothing useful to do until one exists. Saying so
                // beats an empty select.
                ->disabled(fn (): bool => ! $this->hasAnyCategory())
                ->tooltip(fn (): ?string => $this->hasAnyCategory()
                    ? null
                    : 'Add a menu and a section to it first.')
                ->mutateDataUsing(fn (array $data): array => MenuItemForm::storePrice($data)),
        ];
    }

    /**
     * Whether this restaurant has anywhere to put a dish yet.
     */
    private function hasAnyCategory(): bool
    {
        return MenuCategory::query()
            ->where('restaurant_id', Filament::getTenant()?->getKey())
            ->exists();
    }
}
