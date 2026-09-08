<?php

namespace App\Filament\Admin\Resources\MenuItems\Schemas;

use App\Enums\Currency;
use App\Enums\FoodType;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dish')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                                'restaurant_id',
                                self::tenantKey(),
                            ))
                            ->columnSpanFull(),

                        // Only this restaurant's sections are offered, and the
                        // composite foreign key refuses anything else even if
                        // the submitted id is tampered with.
                        Select::make('menu_category_id')
                            ->label('Section')
                            ->options(fn (): array => MenuCategory::query()
                                ->where('restaurant_id', self::tenantKey())
                                ->inMenuOrder()
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->prefixIcon(Heroicon::OutlinedRectangleStack)
                            ->helperText('Hiding a section hides everything in it, this dish included.'),

                        Select::make('food_type')
                            ->label('Food type')
                            ->options(FoodType::options())
                            ->required()
                            ->default(FoodType::Vegetarian->value)
                            ->native(false)
                            ->helperText('Shown to guests as the veg or non-veg mark.'),

                        Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional. What the dish is, in a line or two.'),
                    ])
                    ->columns(2),

                Section::make('Price and availability')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema([
                        // Typed and shown in major units, stored as an integer
                        // count of minor units. App\Enums\Currency does the
                        // conversion, and this is the only place it happens for
                        // this form — so no float ever reaches the database.
                        TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999)
                            ->step(0.01)
                            ->prefix(fn (): string => self::currency()->symbol())
                            ->helperText('What a guest pays, in whole currency. Stored exactly, never as a float.'),

                        Toggle::make('is_available')
                            ->label('Available now')
                            ->default(true)
                            ->helperText('Turn off when it sells out, without taking it off the menu.'),

                        TextInput::make('position')
                            ->label('Order in the section')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->required()
                            ->prefixIcon(Heroicon::OutlinedBars3BottomLeft),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Turn the typed major-unit price into the integer that gets stored.
     *
     * Both create and edit go through here, so the rounding happens exactly
     * once per save and no float is ever handed to the database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function storePrice(array $data): array
    {
        $data['price_minor_units'] = self::currency()->toMinorUnits($data['price'] ?? 0);

        unset($data['price']);

        return $data;
    }

    /**
     * Turn the stored integer back into the value the form edits.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillPrice(array $data): array
    {
        $data['price'] = self::currency()->toMajorUnits((int) ($data['price_minor_units'] ?? 0));

        return $data;
    }

    /**
     * The currency this restaurant prices in.
     */
    public static function currency(): Currency
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant
            ? $tenant->currency()
            : Currency::IndianRupee;
    }

    /**
     * The restaurant the panel is serving, if there is one.
     */
    private static function tenantKey(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant ? $tenant->getKey() : null;
    }
}
