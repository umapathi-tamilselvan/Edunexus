<?php

namespace App\Domain\Academic\Enums;

/**
 * ISO weekday numbers, as stored in timetable_entries.weekday.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return $this->name;
    }
}
