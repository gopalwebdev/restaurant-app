<?php

namespace App\Models\Concerns;

use App\Enums\Currency;
use App\Enums\TaxRate;
use App\Models\Restaurant;
use App\Models\RestaurantSetting;

/**
 * Something a guest can buy off a menu: a dish, or a combo of them.
 *
 * Both carry the same four things — a price in minor units, an optional higher
 * price shown struck through beside it, an optional GST slab of their own, and
 * a currency that belongs to the restaurant rather than to them. This is where
 * that behaviour lives once, so the two models cannot drift on how a price is
 * rounded or which rate applies.
 *
 * Using models must have `price_minor_units`, `strike_price_minor_units` and
 * `tax_rate_basis_points` columns, and a `tenant_id`.
 *
 * Every reader here takes an optional override, and lists should pass one.
 * Resolving the currency or the tax rate per row is a query per row that
 * answers the same thing for every one of them — every dish on a menu shares
 * one restaurant. See .ai/rules/models.md.
 */
trait IsPricedOnAMenu
{
    /**
     * The currency this is priced in.
     *
     * Deliberately never reaches through $this->restaurant: that is a lazy
     * load, which Model::shouldBeStrict() turns into an exception outside
     * production and which is an N+1 down a list of dishes inside it. A loaded
     * relation is used when it is there, and otherwise this asks for the one
     * column it needs.
     */
    public function currency(): Currency
    {
        $restaurant = $this->relationLoaded('restaurant') ? $this->getRelation('restaurant') : null;

        if ($restaurant instanceof Restaurant) {
            return $restaurant->currency();
        }

        // value() on an Eloquent builder applies the model's cast, so this
        // comes back as the enum already. A restaurant with no settings row
        // yet has no currency, and falls back to the default.
        $stored = RestaurantSetting::query()
            ->where('tenant_id', $this->tenant_id)
            ->value('currency');

        return $stored instanceof Currency ? $stored : Currency::IndianRupee;
    }

    /**
     * The GST slab this is taxed at.
     *
     * A row of its own overrides, and null means "whatever the restaurant
     * charges" — which is the answer for almost everything on a menu, so the
     * rate is set once in settings rather than on every dish.
     *
     * Pass $default when rendering a list; every row on a menu shares it.
     */
    public function taxRate(?TaxRate $default = null): TaxRate
    {
        $own = $this->tax_rate_basis_points;

        if ($own instanceof TaxRate) {
            return $own;
        }

        if ($default instanceof TaxRate) {
            return $default;
        }

        $restaurant = $this->relationLoaded('restaurant') ? $this->getRelation('restaurant') : null;

        if ($restaurant instanceof Restaurant) {
            return $restaurant->taxRate();
        }

        $stored = RestaurantSetting::query()
            ->where('tenant_id', $this->tenant_id)
            ->value('tax_rate_basis_points');

        return $stored instanceof TaxRate ? $stored : TaxRate::default();
    }

    /**
     * Whether a higher price is shown struck through beside the real one.
     */
    public function hasStrikePrice(): bool
    {
        return $this->strike_price_minor_units !== null
            && $this->strike_price_minor_units > $this->price_minor_units;
    }

    /**
     * What is knocked off, in minor units, or zero when nothing is.
     */
    public function discountMinorUnits(): int
    {
        return $this->hasStrikePrice()
            ? $this->strike_price_minor_units - $this->price_minor_units
            : 0;
    }

    /**
     * The price as money, in the restaurant's own currency.
     *
     * For the Filament tables, which are server rendered. The guest app is sent
     * the integer and formats it itself — see .ai/rules/js.md.
     */
    public function formattedPrice(?Currency $currency = null): string
    {
        return ($currency ?? $this->currency())->format($this->price_minor_units);
    }

    /**
     * The struck-through price as money, or null when there is not one.
     */
    public function formattedStrikePrice(?Currency $currency = null): ?string
    {
        $strike = $this->strike_price_minor_units;

        // Read into a local rather than checked through hasStrikePrice(): the
        // two say the same thing, but only this makes the value non-null to a
        // reader and to static analysis.
        if ($strike === null || $strike <= $this->price_minor_units) {
            return null;
        }

        return ($currency ?? $this->currency())->format($strike);
    }
}
