<?php

namespace App\Filament\SuperAdmin\Resources\Restaurants\Schemas;

use App\Enums\CountryCallingCode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->description('What this restaurant is called, and where it is served from.')
                    ->schema([
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
                            ->helperText('Turning this off takes the storefront offline.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Where it trades')
                    ->schema([
                        TextInput::make('address')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('pincode')
                            ->label('Pincode')
                            ->required()
                            ->maxLength(16),
                    ])
                    ->columns(2),

                // The platform's own record of how to reach whoever runs this
                // restaurant. What guests see is on the restaurant's settings
                // page, which the restaurant edits itself.
                Section::make('How the platform reaches them')
                    ->description('Not shown to guests: the storefront contact details are on the restaurant’s own settings page.')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        // Stored as two columns: the calling code, and the
                        // national number on its own. Only India is served for
                        // now, so the code is a one-option select rather than
                        // something to type wrongly.
                        Select::make('phone_country_code')
                            ->label('Country code')
                            ->options(CountryCallingCode::options())
                            ->default(CountryCallingCode::India->value)
                            ->selectablePlaceholder(false)
                            ->required(),

                        TextInput::make('phone')
                            ->label('Mobile number')
                            ->tel()
                            ->required()
                            ->rule('digits:'.CountryCallingCode::India->mobileNumberLength())
                            ->maxLength(CountryCallingCode::longestMobileNumberLength())
                            ->helperText(sprintf('%d digits, without the country code.', CountryCallingCode::India->mobileNumberLength())),

                        Select::make('secondary_phone_country_code')
                            ->label('Secondary country code')
                            ->options(CountryCallingCode::options())
                            ->requiredWith('secondary_phone')
                            // A code with no number behind it says nothing, so
                            // it is stored only while there is one.
                            ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => filled($get('secondary_phone')) ? $state : null),

                        TextInput::make('secondary_phone')
                            ->label('Secondary mobile number')
                            ->tel()
                            ->rule('digits:'.CountryCallingCode::India->mobileNumberLength())
                            ->maxLength(CountryCallingCode::longestMobileNumberLength()),
                    ])
                    ->columns(2),
            ]);
    }
}
