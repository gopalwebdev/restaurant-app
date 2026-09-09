<?php

namespace App\Filament\SuperAdmin\Resources\Restaurants\Schemas;

use App\Enums\CountryCallingCode;
use App\Enums\Role as RoleEnum;
use App\Models\Restaurant;
use Closure;
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

                Section::make('Limits')
                    ->description('How many accounts may hold each role here.')
                    ->schema([
                        TextInput::make('max_admins')
                            ->label('Max admins')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(fn (): int => (int) config('restaurants.default_max_admins'))
                            ->required()
                            ->helperText('At least one restaurant admin is required.')
                            ->rule(fn (?Restaurant $record): Closure => self::notBelowCurrentHolders($record, RoleEnum::Admin)),

                        TextInput::make('max_staff')
                            ->label('Max staff')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(fn (): int => (int) config('restaurants.default_max_staff'))
                            ->required()
                            ->helperText('How many floor staff accounts this restaurant may have at once.')
                            ->rule(fn (?Restaurant $record): Closure => self::notBelowCurrentHolders($record, RoleEnum::Staff)),
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

    /**
     * Refuse a limit lower than the roster it would already break.
     *
     * A restaurant with 3 staff may not be dropped to a limit of 2 — the panel
     * says how many to remove first rather than silently locking the extra
     * ones out of a role they still hold. $record is null while creating,
     * where a fresh restaurant has no roster yet to break.
     */
    private static function notBelowCurrentHolders(?Restaurant $record, RoleEnum $role): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($record, $role): void {
            if (! $record instanceof Restaurant) {
                return;
            }

            $current = $record->roleHolderCount($role);
            $limit = (int) $value;

            if ($current <= $limit) {
                return;
            }

            $noun = $role === RoleEnum::Admin
                ? ($current === 1 ? 'admin' : 'admins')
                : ($current === 1 ? 'staff member' : 'staff members');

            $fail(sprintf(
                'This restaurant has %d %s. Remove %d before lowering the limit to %d.',
                $current,
                $noun,
                $current - $limit,
                $limit,
            ));
        };
    }
}
