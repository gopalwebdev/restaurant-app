<?php

namespace App\Actions\Ordering;

use App\Enums\ListMove;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Trade one record's place in a hand-arranged list with its neighbour's.
 *
 * The panel arranges every ordered list this way — a step at a time, with two
 * buttons — rather than by typing numbers into a position field. Nobody edits a
 * menu by working out that the starters should be 30 and the desserts 40.
 *
 * $siblings is the list this record is arranged within, and it differs per
 * table: the sections of one menu, the dishes of one section, the rows of one
 * home screen, the tiles of one row. The caller narrows it, because only the
 * caller knows.
 *
 * Positions are normalised before the swap. They arrive from seeds, imports and
 * years of editing as duplicates and gaps, and a swap between two records that
 * both sit at 0 would move nothing at all.
 */
class MoveRecordInList
{
    /**
     * @param  Builder<covariant Model>  $siblings  the list this record is arranged within
     */
    public function __invoke(Model $record, Builder $siblings, ListMove $direction, string $column = 'position'): void
    {
        DB::transaction(function () use ($record, $siblings, $direction, $column): void {
            $this->normalise($siblings, $column);

            $position = (int) (clone $siblings)->whereKey($record->getKey())->value($column);

            $neighbour = (clone $siblings)
                ->where($column, $direction->comparison(), $position)
                ->orderBy($column, $direction->neighbourOrder())
                ->first();

            // Already at the end it was being pushed towards.
            if (! $neighbour instanceof Model) {
                return;
            }

            $neighbourPosition = (int) $neighbour->getAttribute($column);

            (clone $siblings)->whereKey($record->getKey())->update([$column => $neighbourPosition]);
            (clone $siblings)->whereKey($neighbour->getKey())->update([$column => $position]);
        });
    }

    /**
     * Give every record in the list a distinct position, keeping the order they
     * are already read in.
     *
     * @param  Builder<covariant Model>  $siblings
     */
    private function normalise(Builder $siblings, string $column): void
    {
        (clone $siblings)
            ->orderBy($column)
            ->orderBy((clone $siblings)->getModel()->getKeyName())
            ->get()
            ->each(function (Model $sibling, int $index) use ($siblings, $column): void {
                if ((int) $sibling->getAttribute($column) === $index) {
                    return;
                }

                (clone $siblings)->whereKey($sibling->getKey())->update([$column => $index]);
            });
    }
}
