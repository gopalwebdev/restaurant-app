<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedNames;
use Carbon\CarbonImmutable;
use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of one of a restaurant's menus: Starters, Biryani, Desserts.
 *
 * Categories are ordered by hand rather than alphabetically, because a menu is
 * read in the order the restaurant means it to be read.
 *
 * menu_id is carried alongside tenant_id and the pair is a composite
 * foreign key into menus, so a section can never end up under another
 * restaurant's menu. The name is translated — see HasTranslatedNames.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_id
 * @property-read Menu $menu
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['menu_id', 'name', 'position', 'is_active'])]
class MenuCategory extends Model
{
    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

    use HasTranslatedNames;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * Categories are new and hidden until told otherwise is the wrong default,
     * so an unsaved one already reads as active under Model::shouldBeStrict().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    /**
     * Take the restaurant from the menu this sits on.
     *
     * The same restaurant by definition — the composite foreign key insists on
     * it — so nothing that creates a category has to remember. Filament's
     * tenancy stamps the model a *resource* is saving, and categories are no
     * longer edited through one: they are created by
     * CategoriesRelationManager on the menu's own page, which writes through
     * Menu::menuCategories() and so sets only menu_id. Same shape and same
     * reason as MenuItemAddition::booted() and HomeTile::booted().
     */
    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (filled($category->tenant_id) || blank($category->menu_id)) {
                return;
            }

            $category->tenant_id = Menu::query()
                ->withoutGlobalScopes()
                ->whereKey($category->menu_id)
                ->value('tenant_id');
        });
    }

    /**
     * The restaurant whose menu this belongs to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'tenant_id');
    }

    /**
     * The menu this section appears on.
     *
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * The subdivisions of this category, when it has any.
     *
     * Most categories have none. A restaurant subdivides "Biryani" into
     * Chicken, Mutton and Vegetable only when the category is long enough to
     * be worth breaking up.
     *
     * @return HasMany<MenuSubCategory, $this>
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(MenuSubCategory::class);
    }

    /**
     * The items filed under this category.
     *
     * Every dish in the category, including those sitting in one of its
     * sub-categories — a dish keeps its menu_category_id whether or not it is
     * subdivided, which is exactly why this relation still answers for the
     * whole branch.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * The items filed straight under this category, not in a sub-category.
     *
     * What the guest's menu lists directly beneath the category heading,
     * above its subdivisions.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function directMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('menu_sub_category_id');
    }

    /**
     * Limit the query to categories a guest should see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
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
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
