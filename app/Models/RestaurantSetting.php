<?php

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\RestaurantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one restaurant is configured.
 *
 * Exactly one row per restaurant, enforced by a unique key on restaurant_id.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string $timezone
 * @property Currency $currency
 * @property bool $accepts_orders
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'contact_email',
    'contact_phone',
    'timezone',
    'currency',
    'accepts_orders',
    'opens_at',
    'closes_at',
])]
class RestaurantSetting extends Model
{
    /** @use HasFactory<RestaurantSettingFactory> */
    use HasFactory;

    /**
     * The restaurant these settings belong to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'accepts_orders' => 'boolean',
        ];
    }
}
