<?php

namespace App\Models;

use App\Enums\AdminPanel;
use App\Enums\CountryCallingCode;
use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
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
     * The menus this restaurant serves.
     *
     * @return HasMany<Menu, $this>
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /**
     * The tiles a guest lands on after scanning a table's QR code.
     *
     * @return HasMany<HomeTile, $this>
     */
    public function homeTiles(): HasMany
    {
        return $this->hasMany(HomeTile::class);
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
     * The currency this restaurant prices in.
     *
     * Every price on a menu shares one, so resolve it once and pass it down
     * rather than asking per dish. Reaching through $this->settings would be a
     * lazy load, which Model::shouldBeStrict() turns into an exception outside
     * production, so a settings row that was not eager loaded is fetched by the
     * single column this needs.
     */
    public function currency(): Currency
    {
        if ($this->relationLoaded('settings')) {
            $settings = $this->getRelation('settings');

            return $settings instanceof RestaurantSetting ? $settings->currency : Currency::IndianRupee;
        }

        $stored = RestaurantSetting::query()
            ->where('restaurant_id', $this->getKey())
            ->value('currency');

        return $stored instanceof Currency ? $stored : Currency::IndianRupee;
    }

    /**
     * Whether this restaurant is taking new orders right now.
     *
     * Resolved the same way as currency(), and for the same reason: reaching
     * through $this->settings is a lazy load, which Model::shouldBeStrict()
     * turns into an exception outside production.
     */
    public function isAcceptingOrders(): bool
    {
        if ($this->relationLoaded('settings')) {
            $settings = $this->getRelation('settings');

            return $settings instanceof RestaurantSetting && $settings->accepts_orders;
        }

        return (bool) RestaurantSetting::query()
            ->where('restaurant_id', $this->getKey())
            ->value('accepts_orders');
    }

    /**
     * Where this restaurant's staff sign in.
     *
     * The panel's own login route carries no domain — signing in happens before
     * a tenant is known, so it answers on any host — which means the subdomain
     * has to be put on here, from the slug that defines it. The path comes from
     * AdminPanel so it cannot drift from the panel it opens.
     */
    public function adminSignInUrl(): string
    {
        return sprintf(
            '%s://%s.%s/%s/login',
            str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http',
            $this->slug,
            config('app.domain'),
            AdminPanel::Admin->path(),
        );
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
