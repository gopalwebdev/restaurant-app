<?php

namespace App\Filament\SuperAdmin\Resources\Users\Schemas;

use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->description('Who this is and where their sign-in codes go.')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedUser),

                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                            ->helperText('Accounts carry no password. Sign-in codes are emailed here.'),
                    ])
                    ->columns(2),

                Section::make('Placement')
                    ->description('Which restaurant this account belongs to, and whether it is on the product team.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        // Null is what "Product team" means on the users table. It
                        // grants nothing by itself, which is why the toggle
                        // below is a separate answer rather than derived here.
                        // Options rather than ->relationship(): the create and
                        // edit pages hand the id to an action that writes the
                        // column and the restaurant roster together, and a
                        // bound relationship would write the column on its own.
                        Select::make('tenant_id')
                            ->label('Restaurant')
                            ->options(fn (): array => Restaurant::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('Product team — no restaurant')
                            ->prefixIcon(Heroicon::OutlinedBuildingStorefront)
                            ->helperText('Leave empty for the product team. Setting it also puts them on that restaurant\'s roster.'),

                        Toggle::make('is_super_admin')
                            ->label('The product team')
                            ->inline(false)
                            ->helperText('Grants every permission on every restaurant. This, not an empty restaurant, is what makes a super admin.')
                            ->disabled(fn (?User $record): bool => Filament::auth()->user()?->is($record) ?? false)
                            ->dehydrated(fn (?User $record): bool => ! (Filament::auth()->user()?->is($record) ?? false)),
                    ])
                    ->columns(2),

                Section::make('Roles')
                    ->description('What this account may do inside a restaurant.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        // Every role, including any carrying a product team
                        // permission: deciding that is exactly what this panel
                        // is for. A restaurant panel is offered a filtered list
                        // instead, by SetRestaurantUserRoles.
                        CheckboxList::make('roles')
                            ->options(fn (): array => Role::query()
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->all())
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->columnSpanFull()
                            ->helperText('Roles are held per account, not per restaurant: someone staffing two restaurants carries these at both.'),
                    ]),
            ]);
    }
}
