<?php

namespace App\Domain\Attendance\Enums;

/**
 * How a student attended a day.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Leave = 'leave';

    /**
     * How much of a day this counts as attended: late is a full day, half
     * a day is half, and absent is none.
     */
    public function attended(): float
    {
        return match ($this) {
            self::Present, self::Late => 1.0,
            self::HalfDay => 0.5,
            self::Absent, self::Leave => 0.0,
        };
    }

    /**
     * Whether the day counts in an attendance percentage at all. Leave is
     * excused, so it is left out of both the attended days and the days
     * marked.
     */
    public function countsInPercentage(): bool
    {
        return $this !== self::Leave;
    }
}
