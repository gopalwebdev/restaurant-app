<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\TaxRate;
use Carbon\CarbonImmutable;
use Database\Factories\RestaurantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one restaurant is configured.
 *
 * Exactly one row per restaurant, enforced by a unique key on tenant_id.
 *
 * It carries the tax and the charges every price on the menu is read against:
 * the default GST slab a dish falls back to, whether the prices already include
 * it, and the two optional charges. Each charge is a switch and an amount
 * rather than an amount alone, so "we do not levy a service charge" is a thing
 * a restaurant can say — which matters here, because the CCPA's 2022 guidelines
 * make a service charge voluntary rather than something a bill may assume.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property Currency $currency
 * @property string|null $gstin
 * @property TaxRate $tax_rate_basis_points
 * @property bool $prices_include_tax
 * @property bool $service_charge_enabled
 * @property int $service_charge_basis_points
 * @property bool $parcel_charge_enabled
 * @property int $parcel_charge_minor_units
 * @property bool $accepts_orders
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'contact_email',
    'contact_phone',
    'currency',
    'gstin',
    'tax_rate_basis_points',
    'prices_include_tax',
    'service_charge_enabled',
    'service_charge_basis_points',
    'parcel_charge_enabled',
    'parcel_charge_minor_units',
    'accepts_orders',
    'opens_at',
    'closes_at',
])]
class RestaurantSetting extends Model
{
    /** @use HasFactory<RestaurantSettingFactory> */
    use HasFactory;

    /**
     * The database defaults only land on insert, so an unsaved row would throw
     * under Model::shouldBeStrict() when the settings page reads it before the
     * first save. Same reason as User::$attributes.
     *
     * The tax rate is spelled as the enum case rather than as TaxRate::default(),
     * because a property initialiser may only hold a constant expression and a
     * static call is not one. The two have to agree, and a test in
     * RestaurantSettingsTest asserts they do.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => Currency::IndianRupee->value,
        'tax_rate_basis_points' => TaxRate::Five->value,
        'prices_include_tax' => false,
        'service_charge_enabled' => false,
        'service_charge_basis_points' => 0,
        'parcel_charge_enabled' => false,
        'parcel_charge_minor_units' => 0,
        'accepts_orders' => true,
    ];

    /**
     * The restaurant these settings belong to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'tenant_id');
    }

    /**
     * The GST slab a dish falls back to when it names none of its own.
     */
    public function taxRate(): TaxRate
    {
        return $this->tax_rate_basis_points;
    }

    /**
     * The service charge on an amount, or zero when it is switched off.
     *
     * A percentage of what has been ordered, in the same minor units, rounded
     * once. Switched off is not the same as set to nothing: the switch is what
     * lets a bill say the restaurant does not levy one at all.
     */
    public function serviceChargeOn(int $minorUnits): int
    {
        if (! $this->service_charge_enabled || $this->service_charge_basis_points === 0) {
            return 0;
        }

        return (int) round(
            $minorUnits * $this->service_charge_basis_points / TaxRate::BASIS_POINTS_PER_WHOLE,
        );
    }

    /**
     * The flat charge for packing an order to take away, or zero when off.
     */
    public function parcelCharge(): int
    {
        return $this->parcel_charge_enabled ? $this->parcel_charge_minor_units : 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'tax_rate_basis_points' => TaxRate::class,
            'prices_include_tax' => 'boolean',
            'service_charge_enabled' => 'boolean',
            'service_charge_basis_points' => 'integer',
            'parcel_charge_enabled' => 'boolean',
            'parcel_charge_minor_units' => 'integer',
            'accepts_orders' => 'boolean',
        ];
    }
}
