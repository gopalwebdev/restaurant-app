<?php

namespace App\Filament\SuperAdmin\Resources\Permissions\Pages;

use App\Filament\SuperAdmin\Resources\Permissions\PermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New permission')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
