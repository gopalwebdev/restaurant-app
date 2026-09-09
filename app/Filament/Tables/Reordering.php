<?php

namespace App\Filament\Tables;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * How every hand-arranged table is put in order.
 *
 * Filament's own drag and drop, with its trigger button relabelled so the two
 * states say what they are: "Rearrange" puts the table into drag mode, and
 * "Done" takes it back out. Rows are saved as they are dropped rather than on
 * the way out — the second press confirms the admin has finished, it is not
 * what commits the change.
 *
 * The pair of arrow buttons this replaced were a row of chevrons on every line
 * that had to be clicked once per place moved; dragging a section from the
 * bottom of a long menu to the top is one gesture.
 */
final class Reordering
{
    /**
     * The trigger that turns drag mode on and off.
     *
     * @return Closure(Action, bool): Action
     */
    public static function trigger(): Closure
    {
        return static fn (Action $action, bool $isReordering): Action => $action
            ->button()
            ->outlined(! $isReordering)
            ->color($isReordering ? 'success' : 'gray')
            ->icon($isReordering ? Heroicon::OutlinedCheck : Heroicon::OutlinedArrowsUpDown)
            ->label($isReordering ? __('panel.shared.rearrange_done') : __('panel.shared.rearrange'));
    }
}
