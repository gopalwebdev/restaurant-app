<?php

namespace App\Enums;

/**
 * Which way a record is being nudged in a hand-arranged list.
 *
 * The panel arranges menus, sections, dishes, home screen rows and their tiles
 * by moving one record at a time rather than by typing numbers into a position
 * field. Two directions, named, so nothing reads `move($record, -1)`.
 */
enum ListMove: string
{
    case Up = 'up';

    case Down = 'down';

    /**
     * Whether this direction goes towards the front of the list.
     */
    public function isTowardsFront(): bool
    {
        return $this === self::Up;
    }

    /**
     * The comparison that finds the neighbour being traded places with.
     */
    public function comparison(): string
    {
        return $this->isTowardsFront() ? '<' : '>';
    }

    /**
     * How to order the siblings so the nearest neighbour comes first.
     */
    public function neighbourOrder(): string
    {
        return $this->isTowardsFront() ? 'desc' : 'asc';
    }
}
