<?php

namespace App\Filament\SuperAdmin\Resources\Roles;

use App\Filament\SuperAdmin\Resources\Roles\Pages\CreateRole;
use App\Filament\SuperAdmin\Resources\Roles\Pages\EditRole;
use App\Filament\SuperAdmin\Resources\Roles\Pages\ListRoles;
use App\Filament\SuperAdmin\Resources\Roles\Pages\ViewRole;
use App\Filament\SuperAdmin\Resources\Roles\Schemas\RoleForm;
use App\Filament\SuperAdmin\Resources\Roles\Schemas\RoleInfolist;
use App\Filament\SuperAdmin\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The roles every restaurant assigns from, on the platform panel.
 *
 * Access is RolePolicy's business. What this class adds is the one rule a
 * policy cannot express here: a built-in role — one declared in App\Enums\Role
 * — is read-only, and that has to hold for a super admin too. The Gate::before
 * in AppServiceProvider answers true for platform staff before any policy
 * runs, so the guard is stated here, where Filament asks before it renders an
 * action or a page.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Access control';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return (! $record instanceof Role || ! $record->isBuiltIn()) && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return (! $record instanceof Role || ! $record->isBuiltIn()) && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
