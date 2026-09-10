<?php

namespace App\Filament\SuperAdmin\Resources\Roles\Pages;

use App\Filament\SuperAdmin\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (Role $record): bool => RoleResource::canEdit($record)),
        ];
    }
}
