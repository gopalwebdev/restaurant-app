<?php

namespace App\Models;

use App\Enums\FoodType;
use App\Enums\ItemAvailability;
use App\Enums\TaxRate;
use App\Models\Concerns\HasTranslatedNames;
use App\Models\Concerns\IsPricedOnAMenu;
use Carbon\CarbonImmutable;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dish on one restaurant's menu.
 *
 * The price is an integer count of the currency's minor unit, never a float:
 * ₹249.50 is stored as 24950. App\Enums\Currency converts at the edges, and
 * the currency itself comes from the restaurant's settings, so nothing here
 * assumes rupees. `strike_price_minor_units` is the higher price shown struck
 * through beside it and is null on almost every dish — see IsPricedOnAMenu.
 *
 * A dish is always filed under a **category**, and optionally under one of that
 * category's **sub-categories**. Both columns are kept because the pair is a
 * composite foreign key into menu_sub_categories (id, menu_category_id), which
 * is what makes it a database error for a dish to sit in a sub-category
 * belonging to some other category. Where the dish appears on the menu is the
 * sub-category when it has one and the category otherwise — `section()` is the
 * one place that decides.
 *
 * tenant_id is carried directly as well as through the category. That is
 * deliberate — it is the tenant boundary, and a composite foreign key on
 * (menu_category_id, tenant_id) makes it impossible for the two to
 * disagree.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_category_id
 * @property int|null $menu_sub_category_id
 * @property-read MenuCategory $menuCategory
 * @property-read MenuSubCategory|null $menuSubCategory
 * @property string $name
 * @property string|null $description
 * @property int $price_minor_units
 * @property int|null $strike_price_minor_units
 * @property TaxRate|null $tax_rate_basis_points
 * @property string|null $hsn_code
 * @property FoodType $food_type
 * @property ItemAvailability $availability
 * @property bool $is_featured
 * @property int $featured_position
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'menu_category_id',
    'menu_sub_category_id',
    'name',
    'description',
    'price_minor_units',
    'strike_price_minor_units',
    'tax_rate_basis_points',
    'hsn_code',
    'food_type',
    'availability',
    'is_featured',
    'featured_position',
    'position',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use HasTranslatedNames;
    use IsPricedOnAMenu;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'availability' => ItemAvailability::Available->value,
        'is_featured' => false,
        'featured_position' => 0,
    ];

    /**
     * The restaurant selling this.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'tenant_id');
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
     * The subdivision of that section, when the category has been subdivided.
     *
     * @return BelongsTo<MenuSubCategory, $this>
     */
    public function menuSubCategory(): BelongsTo
    {
        return $this->belongsTo(MenuSubCategory::class);
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
     * The lines of every combo this dish appears in.
     *
     * @return HasMany<MenuComboItem, $this>
     */
    public function comboItems(): HasMany
    {
        return $this->hasMany(MenuComboItem::class);
    }

    /**
     * The combos this dish is part of.
     *
     * @return BelongsToMany<MenuCombo, $this>
     */
    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(MenuCombo::class, 'menu_combo_items')
            ->withPivot(['quantity', 'position'])
            ->withTimestamps();
    }

    /**
     * Where this dish appears on the menu.
     *
     * The sub-category when it has one, the category otherwise — the single
     * place that rule is stated, so the panel's tree, the guest's menu and the
     * "move to section" action cannot disagree about where a dish lives.
     *
     * Reads loaded relations only; a caller that has not loaded them gets the
     * category it already holds rather than a lazy load, which
     * Model::shouldBeStrict() would throw on anyway.
     */
    public function section(): MenuCategory|MenuSubCategory
    {
        $subCategory = $this->relationLoaded('menuSubCategory')
            ? $this->getRelation('menuSubCategory')
            : null;

        return $subCategory instanceof MenuSubCategory ? $subCategory : $this->menuCategory;
    }

    /**
     * Whether a guest may order this right now.
     */
    public function isOrderable(): bool
    {
        return $this->availability->isOrderable();
    }

    /**
     * Limit the query to what a guest may actually order right now.
     *
     * Every level matters: hiding a whole menu has to take its sections, their
     * subdivisions and all the dishes with it. The sub-category clause is
     * written as "has none, or has an active one" because the column is
     * nullable — a plain whereRelation would drop every dish that is not in a
     * sub-category, which is most of them.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOrderable(Builder $query): void
    {
        $query->whereIn('availability', ItemAvailability::orderableValues())
            ->whereRelation('menuCategory', 'is_active', true)
            ->whereRelation('menuCategory.menu', 'is_active', true)
            ->where(fn (Builder $withinSection): Builder => $withinSection
                ->whereNull('menu_sub_category_id')
                ->orWhereRelation('menuSubCategory', 'is_active', true));
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
     * Limit the query to the dishes on one menu.
     *
     * A dish reaches its menu through its category, so this states that hop
     * once rather than at each call site.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnMenu(Builder $query, int $menuId): void
    {
        $query->whereRelation('menuCategory', 'menu_id', $menuId);
    }

    /**
     * Limit the query to the dishes one menu leads with.
     *
     * Featuring is a flag on the dish, and a dish reaches its menu through its
     * section — so this states that hop once rather than at each call site.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFeaturedOnMenu(Builder $query, int $menuId): void
    {
        $query->where('is_featured', true)->onMenu($menuId);
    }

    /**
     * Order the way the restaurant arranged the dishes it leads with.
     *
     * A separate order from scopeInMenuOrder(): that one places a dish inside
     * its section, this one places it in the featured row, and a dish answers
     * both questions at once.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInFeaturedOrder(Builder $query): void
    {
        $query->orderBy('featured_position')->orderBy(self::fallbackLocalePath());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'strike_price_minor_units' => 'integer',
            'tax_rate_basis_points' => TaxRate::class,
            'food_type' => FoodType::class,
            'availability' => ItemAvailability::class,
            'is_featured' => 'boolean',
            'featured_position' => 'integer',
            'position' => 'integer',
        ];
    }
}
