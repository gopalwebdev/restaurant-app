<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedNames;
use Carbon\CarbonImmutable;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * One of a restaurant's menus: Lunch, Dinner, Drinks.
 *
 * The top of the hierarchy a guest reads down: a menu lists categories, a
 * category lists dishes, and a dish may offer additions. A restaurant that
 * serves one card all day simply has one menu.
 *
 * The name and description are translated columns — one value per
 * App\Enums\Locale case. See App\Models\Concerns\HasTranslatedNames.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'description', 'position', 'is_active'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use HasTranslatedNames;

    /**
     * The columns holding one value per language.
     *
     * @var list<string>
     */
    public array $translatable = ['name', 'description'];

    /**
     * A new menu is showing until it is told otherwise, so an unsaved one reads
     * correctly under Model::shouldBeStrict().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    /**
     * The restaurant this menu belongs to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'tenant_id');
    }

    /**
     * The sections of this menu.
     *
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * The tiles on the home screen that open this menu.
     *
     * @return HasMany<HomeTile, $this>
     */
    public function homeTiles(): HasMany
    {
        return $this->hasMany(HomeTile::class);
    }

    /**
     * Every dish on this menu, whichever section it sits in.
     *
     * Reached through the sections because that is the only path there is —
     * a dish carries its section and its restaurant, never its menu. The
     * featured row on the guest's menu screen and the panel's own reordering
     * of it both read this.
     *
     * @return HasManyThrough<MenuItem, MenuCategory, $this>
     */
    public function menuItems(): HasManyThrough
    {
        return $this->hasManyThrough(MenuItem::class, MenuCategory::class);
    }

    /**
     * Limit the query to menus a guest should see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
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
