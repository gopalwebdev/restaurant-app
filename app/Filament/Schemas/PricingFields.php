<?php

namespace App\Filament\Schemas;

use App\Enums\Currency;
use App\Enums\ItemAvailability;
use App\Enums\TaxRate;
use App\Models\Restaurant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The inputs behind a price: what it costs, what it used to, and its GST slab.
 *
 * A dish and a combo are priced identically, so the fields and — more
 * importantly — the conversion in and out of minor units live here once. Money
 * is typed and shown in major units and stored as an integer count of minor
 * ones (.ai/rules/migrations.md), and `storePrices()` is the only place that
 * rounding happens for either form, so no float ever reaches the database.
 *
 * Every method takes the currency and the restaurant's default tax rate as
 * arguments rather than resolving them per field: they are the same for every
 * row of a form and every row of a table, and asking again per call is a query
 * that answers a question already answered.
 */
final class PricingFields
{
    /**
     * What a guest pays.
     */
    public static function price(Currency $currency): TextInput
    {
        return TextInput::make('price')
            ->label(__('panel.items.price'))
            ->required()
            ->numeric()
            ->minValue(0)
            ->maxValue(99999)
            ->step(0.01)
            ->prefix($currency->symbol())
            ->helperText(__('panel.items.price_help'));
    }

    /**
     * The higher price shown struck through beside it.
     *
     * Optional, and validated to be above the real price rather than merely
     * different: a strike price at or below what is charged advertises a
     * discount that does not exist, which is the one way this field can
     * mislead a guest. Leaving it blank is how a dish stops being on offer —
     * zero would be a price of nothing.
     */
    public static function strikePrice(Currency $currency): TextInput
    {
        return TextInput::make('strike_price')
            ->label(__('panel.items.strike_price'))
            ->numeric()
            ->minValue(0)
            ->maxValue(99999)
            ->step(0.01)
            ->prefix($currency->symbol())
            ->gt('price')
            ->validationMessages(['gt' => __('panel.items.strike_price_invalid')])
            ->helperText(__('panel.items.strike_price_help'));
    }

    /**
     * The GST slab, or nothing to follow the restaurant's own.
     *
     * The placeholder names the restaurant's rate rather than saying "default",
     * so an admin can see what leaving it blank actually charges without
     * opening the settings page.
     */
    public static function taxRate(TaxRate $restaurantRate): Select
    {
        return Select::make('tax_rate_basis_points')
            ->label(__('panel.items.tax_rate'))
            ->options(TaxRate::options())
            ->placeholder(__('panel.items.tax_rate_default', ['rate' => $restaurantRate->label()]))
            ->native(false)
            ->prefixIcon(Heroicon::OutlinedReceiptPercent)
            ->helperText(__('panel.items.tax_rate_help'));
    }

    /**
     * The HSN or SAC code this line carries on a tax invoice.
     */
    public static function hsnCode(): TextInput
    {
        return TextInput::make('hsn_code')
            ->label(__('panel.items.hsn_code'))
            ->maxLength(8)
            ->helperText(__('panel.items.hsn_code_help'));
    }

    /**
     * Whether a guest may order this, and why not when they may not.
     */
    public static function availability(): Select
    {
        return Select::make('availability')
            ->label(__('panel.items.availability'))
            ->options(ItemAvailability::options())
            ->default(ItemAvailability::Available->value)
            ->required()
            ->native(false)
            ->helperText(__('panel.items.availability_help'));
    }

    /**
     * Turn the typed major-unit prices into the integers that get stored.
     *
     * Both create and edit go through here, so the rounding happens exactly
     * once per save. A blank strike price is stored as null rather than zero —
     * null is "not on offer", zero would be a price.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function storePrices(array $data, ?Currency $currency = null): array
    {
        $currency ??= self::currency();

        $data['price_minor_units'] = $currency->toMinorUnits($data['price'] ?? 0);

        $data['strike_price_minor_units'] = blank($data['strike_price'] ?? null)
            ? null
            : $currency->toMinorUnits($data['strike_price']);

        unset($data['price'], $data['strike_price']);

        return $data;
    }

    /**
     * Turn the stored integers back into the values the form edits.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillPrices(array $data, ?Currency $currency = null): array
    {
        $currency ??= self::currency();

        $data['price'] = $currency->toMajorUnits((int) ($data['price_minor_units'] ?? 0));

        $data['strike_price'] = blank($data['strike_price_minor_units'] ?? null)
            ? null
            : $currency->toMajorUnits((int) $data['strike_price_minor_units']);

        return $data;
    }

    /**
     * The currency the restaurant in this panel prices in.
     */
    public static function currency(): Currency
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant ? $tenant->currency() : Currency::IndianRupee;
    }

    /**
     * The GST slab the restaurant in this panel charges by default.
     */
    public static function restaurantTaxRate(): TaxRate
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Restaurant ? $tenant->taxRate() : TaxRate::default();
    }
}
