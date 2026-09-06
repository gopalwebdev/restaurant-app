<?php

namespace App\Filament\SuperAdmin\Resources\Roles\Schemas;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Role names are referenced as strings from code and from the
                // seeder, so they follow one shape: lowercase, hyphenated.
                TextInput::make('name')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->helperText('Lowercase, hyphenated, and permanent: code and the seeder refer to a role by this name.')
                    ->validationMessages([
                        'regex' => 'Use lowercase letters, numbers and hyphens only.',
                    ]),

                CheckboxList::make('permissions')
                    ->relationship(name: 'permissions', titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn (Permission $record): string => $record->label())
                    ->descriptions(fn (): array => self::platformOnlyDescriptions())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->helperText('A role holding any platform permission is never offered inside a restaurant panel.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Mark the permissions that make a role platform-only.
     *
     * @return array<int, string>
     */
    private static function platformOnlyDescriptions(): array
    {
        return Permission::query()
            ->whereIn('name', PermissionEnum::platformOnlyValues())
            ->pluck('name', 'id')
            ->map(fn (): string => 'Platform staff only')
            ->all();
    }
}
