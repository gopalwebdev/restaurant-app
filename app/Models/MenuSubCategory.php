<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedNames;
use Carbon\CarbonImmutable;
use Database\Factories\MenuSubCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subdivision of a category: Biryani → Chicken, Mutton, Vegetable.
 *
 * Optional everywhere. A restaurant that does not subdivide anything never
 * creates one, and its dishes sit straight under their categories exactly as
 * they did before this level existed.
 *
 * A sub-category moves freely between the categories of **one menu** —
 * App\Actions\Menus\MoveSubCategoryToCategory — and never between menus. It has
 * no menu column of its own to move: it reaches its menu through its category,
 * so carrying it to another menu would mean carrying its category, which is
 * what MoveCategoryToMenu already does for the whole branch at once.
 *
 * menu_category_id is carried alongside tenant_id and the pair is a composite
 * foreign key into menu_categories, so a sub-category can never end up under
 * another restaurant's category. The name is translated — see
 * HasTranslatedNames.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $menu_category_id
 * @property-read MenuCategory $menuCategory
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['menu_category_id', 'name', 'position', 'is_active'])]
class MenuSubCategory extends Model
{
    /** @use HasFactory<MenuSubCategoryFactory> */
    use HasFactory;

    use HasTranslatedNames;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    /**
     * Take the restaurant from the category this belongs to.
     *
     * The same restaurant by definition — the composite foreign key insists on
     * it — so deriving it here means nothing that creates a sub-category has to
     * remember. That includes the relation manager on the menu page: Filament's
     * tenancy stamps the model a resource is saving, but not the rows a
     * relation manager writes against a different model. Same reason and same
     * shape as MenuItemAddition::booted().
     */
    protected static function booted(): void
    {
        static::creating(function (self $subCategory): void {
            if (filled($subCategory->tenant_id) || blank($subCategory->menu_category_id)) {
                return;
            }

            $subCategory->tenant_id = MenuCategory::query()
                ->withoutGlobalScopes()
                ->whereKey($subCategory->menu_category_id)
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
     * The category this subdivides.
     *
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * The dishes filed under this sub-category.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Limit the query to sub-categories a guest should see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Limit the query to the sub-categories of one menu.
     *
     * A sub-category has no menu column — it reaches one through its category —
     * so this states that hop once rather than at each call site.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnMenu(Builder $query, int $menuId): void
    {
        $query->whereRelation('menuCategory', 'menu_id', $menuId);
    }

    /**
     * Order the way the restaurant arranged them, name only to break ties.
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
