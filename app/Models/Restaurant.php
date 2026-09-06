<?php

namespace App\Models;

use App\Enums\CountryCallingCode;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A single restaurant, which is also the tenant boundary.
 *
 * The slug doubles as the subdomain, so `t1` is served at
 * t1.restaurant-app.com and administered at t1.restaurant-app.com/admin.
 *
 * The address and contact details are the platform's record of the business,
 * entered by a super admin when the restaurant is onboarded. What guests see
 * on the storefront lives in RestaurantSetting instead, and the restaurant
 * edits that itself.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $address
 * @property string $pincode
 * @property string|null $email
 * @property CountryCallingCode $phone_country_code
 * @property string $phone
 * @property CountryCallingCode|null $secondary_phone_country_code
 * @property string|null $secondary_phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name', 'address', 'pincode', 'email', 'phone_country_code', 'phone', 'secondary_phone_country_code', 'secondary_phone', 'is_active'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * Bind restaurants by slug so route and tenant resolution share one key.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The staff and administrators attached to this restaurant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * How this restaurant is configured.
     *
     * @return HasOne<RestaurantSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(RestaurantSetting::class);
    }

    /**
     * Limit the query to restaurants that are open for business.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The phone number as it is dialled, calling code and all.
     *
     * The two halves are stored apart so each column holds one fact; putting
     * them back together is a display concern and lives here.
     */
    public function dialablePhone(): string
    {
        return $this->phone_country_code->dialPrefix().' '.$this->phone;
    }

    /**
     * The secondary phone number as it is dialled, where there is one.
     */
    public function dialableSecondaryPhone(): ?string
    {
        if (blank($this->secondary_phone) || ! $this->secondary_phone_country_code instanceof CountryCallingCode) {
            return null;
        }

        return $this->secondary_phone_country_code->dialPrefix().' '.$this->secondary_phone;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_country_code' => CountryCallingCode::class,
            'secondary_phone_country_code' => CountryCallingCode::class,
            'is_active' => 'boolean',
        ];
    }
}
