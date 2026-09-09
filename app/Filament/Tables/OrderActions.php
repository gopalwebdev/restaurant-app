<?php

namespace App\Filament\Tables;

use App\Actions\Ordering\MoveRecordInList;
use App\Enums\ListMove;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The two buttons every hand-arranged table is ordered with.
 *
 * A pair of icon buttons on each row rather than a drag mode and a position
 * field: moving a section one place up is one tap, and the number behind it is
 * never something anyone has to think about. The position column stays on the
 * model — it is what the guest app orders by — it is simply not typed in.
 *
 * $siblings says what this record is arranged *within*, which differs per
 * table: the sections of one menu, the dishes of one section, the rows of one
 * home screen. Ordering across a whole restaurant would let a tap in one menu
 * shuffle another.
 */
final class OrderActions
{
    /**
     * @param  Closure(Model): Builder<covariant Model>  $siblings  the list a record is arranged within
     * @return array<Action>
     */
    public static function make(Closure $siblings, string $column = 'position'): array
    {
        return [
            self::button(ListMove::Up, Heroicon::OutlinedArrowUp, __('panel.shared.move_up'), $siblings, $column),
            self::button(ListMove::Down, Heroicon::OutlinedArrowDown, __('panel.shared.move_down'), $siblings, $column),
        ];
    }

    /**
     * @param  Closure(Model): Builder<covariant Model>  $siblings
     */
    private static function button(
        ListMove $direction,
        Heroicon $icon,
        string $label,
        Closure $siblings,
        string $column,
    ): Action {
        return Action::make('move'.ucfirst($direction->value))
            ->label($label)
            ->iconButton()
            ->icon($icon)
            ->color('gray')
            // The same permission a drag used to need, so a role that may read
            // a menu without rewriting it still cannot rearrange it.
            ->authorize('reorder')
            ->action(function (Model $record) use ($siblings, $direction, $column): void {
                app(MoveRecordInList::class)($record, $siblings($record), $direction, $column);
            });
    }
}
