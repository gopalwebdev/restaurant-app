<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FoodType;
use App\Models\Concerns\HasTranslatedNames;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One dish on one restaurant's menu.
 *
 * The price is an integer count of the currency's minor unit, never a float:
 * ₹249.50 is stored as 24950. App\Enums\Currency converts at the edges, and
 * the currency itself comes from the restaurant's settings, so nothing here
 * assumes rupees.
 *
 * restaurant_id is carried directly as well as through the category. That is
 * deliberate — it is the tenant boundary, and a composite foreign key on
 * (menu_category_id, restaurant_id) makes it impossible for the two to
 * disagree.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $menu_category_id
 * @property string $name
 * @property string|null $description
 * @property int $price_minor_units
 * @property FoodType $food_type
 * @property bool $is_available
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'menu_category_id',
    'name',
    'description',
    'price_minor_units',
    'food_type',
    'is_available',
    'position',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use HasTranslatedNames;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_available' => true,
    ];

    /**
     * The restaurant selling this.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The section of the menu this sits under.
     *
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * The extras this dish may be ordered with.
     *
     * @return HasMany<MenuItemAddition, $this>
     */
    public function additions(): HasMany
    {
        return $this->hasMany(MenuItemAddition::class);
    }

    /**
     * The price as money, in the restaurant's own currency.
     *
     * Pass the currency when rendering a list. Every dish on a menu shares one,
     * so resolving it per item is a query per row that answers the same thing.
     */
    public function formattedPrice(?Currency $currency = null): string
    {
        return ($currency ?? $this->currency())->format($this->price_minor_units);
    }

    /**
     * The currency this item is priced in.
     *
     * Deliberately never reaches through $this->restaurant: that is a lazy load,
     * which Model::shouldBeStrict() turns into an exception outside production
     * and which is an N+1 down a list of dishes inside it. Loaded relations are
     * used when they are there, and otherwise this asks for the one column it
     * needs.
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
            ->where('restaurant_id', $this->restaurant_id)
            ->value('currency');

        return $stored instanceof Currency ? $stored : Currency::IndianRupee;
    }

    /**
     * Limit the query to what a guest may actually order right now.
     *
     * Both halves matter: an item is orderable only if it is available and its
     * whole category is showing.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOrderable(Builder $query): void
    {
        // The same conditions MenuCategory::scopeActive() and
        // Menu::scopeActive() apply, stated here against the relations so the
        // query stays a single statement. All three matter: hiding a whole
        // menu has to take its sections and their dishes with it.
        $query->where('is_available', true)
            ->whereRelation('menuCategory', 'is_active', true)
            ->whereRelation('menuCategory.menu', 'is_active', true);
    }

    /**
     * Order the way the restaurant arranged its menu, name only to break ties.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInMenuOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy(self::fallbackLocalePath());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'food_type' => FoodType::class,
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }
}
