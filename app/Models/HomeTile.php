<?php

namespace App\Models;

use App\Enums\HomeTileAction;
use App\Enums\HomeTileShape;
use App\Models\Concerns\HasTranslatedNames;
use Carbon\CarbonImmutable;
use Database\Factories\HomeTileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One tile on the home screen a guest lands on.
 *
 * A tile is a picture and a destination. The restaurant arranges them, so the
 * order, the pictures and what each one opens are all data rather than a fixed
 * screen — and the guest app draws whatever it is given.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $label
 * @property string|null $image_path
 * @property string|null $document_path
 * @property HomeTileShape $shape
 * @property HomeTileAction $action
 * @property int|null $menu_id
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'label',
    'image_path',
    'document_path',
    'shape',
    'action',
    'menu_id',
    'position',
    'is_active',
])]
class HomeTile extends Model
{
    /** @use HasFactory<HomeTileFactory> */
    use HasFactory;

    use HasTranslatedNames;

    /**
     * @var list<string>
     */
    public array $translatable = ['label'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'shape' => HomeTileShape::Rectangle->value,
        'position' => 0,
        'is_active' => true,
    ];

    /**
     * Keep a tile's action and its destination in step.
     *
     * A Menu tile with no menu, or a PDF tile with no file, is a tile that goes
     * nowhere — and a tile switched from one to the other would otherwise keep
     * the destination it no longer uses. This would be a CHECK constraint if
     * Laravel's Blueprint could express one and SQLite could add one after the
     * table exists; neither is true, so the guard lives here and the admin form
     * states the same rule as validation.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tile): void {
            $required = $tile->action->targetColumn();

            foreach (HomeTileAction::everyTargetColumn() as $column) {
                if ($column !== $required) {
                    $tile->{$column} = null;
                }
            }

            throw_if(
                blank($tile->{$required}),
                LogicException::class,
                sprintf('A %s tile needs a %s.', $tile->action->value, $required),
            );
        });
    }

    /**
     * The restaurant whose home screen this is on.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The menu this opens, where it opens one.
     *
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Whether this tile has a picture to show.
     *
     * A tile without one is not broken: the guest app draws the label on the
     * brand colour instead, so a restaurant can arrange its home screen before
     * it has photography.
     */
    public function hasImage(): bool
    {
        return filled($this->image_path);
    }

    /**
     * Limit the query to tiles a guest should see.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Order the way the restaurant arranged the home screen.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInDisplayOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy(self::fallbackLocalePath('label'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shape' => HomeTileShape::class,
            'action' => HomeTileAction::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
