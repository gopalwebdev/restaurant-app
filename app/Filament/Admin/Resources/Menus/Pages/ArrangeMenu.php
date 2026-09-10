<?php

namespace App\Filament\Admin\Resources\Menus\Pages;

use App\Actions\Menus\ApplyMenuArrangement;
use App\Filament\Admin\Resources\Menus\MenuResource;
use App\Filament\Admin\Resources\Menus\Tables\MenuArrangementTable;
use App\Models\Menu;
use BackedEnum;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use LogicException;

/**
 * The first thing a menu opens on: everything it holds, in the order it is read.
 *
 * A menu used to be edited across four tables that each showed one layer of it,
 * and the order of the featured and combo rails could not be changed at all.
 * This page is one list of the whole menu — both rails, every category, every
 * subdivision and every dish — and one drag puts any of them somewhere else.
 *
 * The table is built on custom data because those rows are three models and two
 * rails; see MenuArrangementTable for what that costs. What it means here is
 * this page owns reordering itself: Filament's own reorderTable() writes one
 * UPDATE over an Eloquent query, and there isn't one.
 */
class ArrangeMenu extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = MenuResource::class;

    protected string $view = 'filament.admin.resources.menus.pages.arrange-menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    public function getTitle(): string
    {
        return (string) __('panel.arrangement.title');
    }

    public function getHeading(): string
    {
        return $this->menu()->name;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('panel.arrangement.title');
    }

    public function table(Table $table): Table
    {
        return MenuArrangementTable::configure($table, $this->menu());
    }

    /**
     * Take the order the drag ended in and write it to the menu.
     *
     * Filament hands over every row of the table in its new order. Working out
     * what that means for four kinds of `position` is
     * App\Actions\Menus\ApplyMenuArrangement's job; this checks that the person
     * dragging may, and asks the table to redraw itself afterwards, which it
     * does not do on its own when the records are custom data.
     *
     * isReorderable() stays first and unchanged: it is the reorder() policy
     * check, and it is the only thing keeping the drag away from someone who
     * may only read the menu (.ai/rules/tables.md).
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        app(ApplyMenuArrangement::class)(
            $this->menu(),
            array_values(array_map(strval(...), $order)),
        );

        $this->resetTable();
    }

    /**
     * Reading the shape of a menu is menu.view; changing it is menu.manage.
     *
     * Deliberately `canView` rather than the `canEdit` the other tabs use.
     * Someone who may only read the menu can see how it is put together — every
     * action on the table is hidden for them and the drag is refused by the
     * reorder policy — and the alternative is a 403 on the tab a menu opens on.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    private function menu(): Menu
    {
        $menu = $this->getRecord();

        return $menu instanceof Menu ? $menu : throw new LogicException('The arrangement page requires a menu.');
    }
}
