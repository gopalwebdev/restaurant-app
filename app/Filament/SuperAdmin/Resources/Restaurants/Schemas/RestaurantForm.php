<?php

namespace App\Filament\SuperAdmin\Resources\Restaurants\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('slug', Str::slug((string) $state));
                    }),

                // The slug is the tenant's subdomain, so it has to stay a valid
                // DNS label: lowercase alphanumerics and inner hyphens only.
                TextInput::make('slug')
                    ->label('Subdomain')
                    ->required()
                    ->maxLength(63)
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/')
                    ->helperText(fn (): string => 'Served at '.config('app.domain').' as <subdomain>.'.config('app.domain'))
                    ->validationMessages([
                        'regex' => 'Use lowercase letters, numbers and hyphens only.',
                    ]),

                Toggle::make('is_active')
                    ->label('Open for business')
                    ->default(true)
                    ->helperText('Turning this off takes the storefront offline.'),
            ]);
    }
}
