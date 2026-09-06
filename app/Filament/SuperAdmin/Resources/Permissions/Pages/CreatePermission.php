<?php

namespace App\Filament\SuperAdmin\Resources\Permissions\Pages;

use App\Filament\SuperAdmin\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;
}
