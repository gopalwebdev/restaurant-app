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
 * @property string|null $available_from
 * @property string|null $available_until
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'description', 'position', 'is_active', 'available_from', 'available_until'])]
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
     * Every subdivision on this menu, whichever category it sits under.
     *
     * Reached through the categories because that is the only path there is —
     * a sub-category carries its category and its restaurant, never its menu.
     * The panel's tree on the menu page reads this.
     *
     * @return HasManyThrough<MenuSubCategory, MenuCategory, $this>
     */
    public function menuSubCategories(): HasManyThrough
    {
        return $this->hasManyThrough(MenuSubCategory::class, MenuCategory::class);
    }

    /**
     * The bundles offered on this menu.
     *
     * A combo hangs off the menu directly rather than off a category — it is
     * something the menu leads with, not something in a section. See MenuCombo.
     *
     * @return HasMany<MenuCombo, $this>
     */
    public function combos(): HasMany
    {
        return $this->hasMany(MenuCombo::class);
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
     * Whether this menu is only served between certain hours.
     *
     * The pair is all-or-nothing, so asking about one answers for both. A menu
     * with no window is served whenever the restaurant is open, which is what
     * most menus do.
     */
    public function hasServiceWindow(): bool
    {
        return filled($this->available_from) && filled($this->available_until);
    }

    /**
     * Whether this menu is being served at a given moment.
     *
     * A menu with no window is always being served. One whose window runs
     * backwards — 22:00 to 02:00, for a late card — wraps past midnight rather
     * than being empty, which is why this is a comparison of two cases and not
     * a single between().
     *
     * The zone is the application's, from APP_TIMEZONE: a restaurant has no
     * timezone of its own here, deliberately (.ai/rules/app.md).
     */
    public function isBeingServedAt(?CarbonImmutable $moment = null): bool
    {
        if (! $this->hasServiceWindow()) {
            return true;
        }

        $from = $this->available_from;
        $until = $this->available_until;

        // hasServiceWindow() has already established both are filled; reading
        // them into locals is what makes that visible here.
        if ($from === null || $until === null) {
            return true;
        }

        $now = ($moment ?? CarbonImmutable::now())->format('H:i:s');
        $from = $this->normalisedTime($from);
        $until = $this->normalisedTime($until);

        return $from <= $until
            ? $now >= $from && $now < $until
            : $now >= $from || $now < $until;
    }

    /**
     * The start of the service window as a clock reading, or null.
     *
     * Postgres hands back "07:00:00" from a time column and SQLite hands back
     * whatever was written, so the raw value is not one shape. These two are
     * what crosses the wire, so the guest app is given one format to render
     * rather than having to cope with both — the same reason prices leave as
     * integers rather than in whichever way a driver stringified them.
     */
    public function servedFrom(): ?string
    {
        return $this->hasServiceWindow() && $this->available_from !== null
            ? $this->clockReading($this->available_from)
            : null;
    }

    /**
     * The end of the service window as a clock reading, or null.
     */
    public function servedUntil(): ?string
    {
        return $this->hasServiceWindow() && $this->available_until !== null
            ? $this->clockReading($this->available_until)
            : null;
    }

    /**
     * A stored time as HH:MM, whatever shape the driver handed back.
     */
    private function clockReading(string $time): string
    {
        return substr($this->normalisedTime($time), 0, 5);
    }

    /**
     * A stored time as HH:MM:SS, whatever shape the driver handed back.
     *
     * Padded to a fixed width before being compared as strings, which is safe
     * for a 24-hour clock and avoids parsing a date that isn't one.
     */
    private function normalisedTime(string $time): string
    {
        return substr($time.':00:00', 0, 8);
    }

    /**
     * Limit the query to menus a guest should see.
     *
     * Deliberately not filtered by the service window: a breakfast menu that
     * has finished is still shown, marked as served 07:00 to 11:00, because a
     * guest looking for it at noon should find it rather than conclude the
     * restaurant has none. isBeingServedAt() is what decides how it reads.
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
