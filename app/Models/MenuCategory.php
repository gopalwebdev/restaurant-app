<?php

namespace App\Models;

use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A section of one restaurant's menu: Starters, Biryani, Desserts.
 *
 * Categories are ordered by hand rather than alphabetically, because a menu is
 * read in the order the restaurant means it to be read.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'is_active'])]
class MenuCategory extends Model
{
    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

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
     * The restaurant whose menu this belongs to.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The items filed under this category.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
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
        $query->orderBy('position')->orderBy('name');
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
