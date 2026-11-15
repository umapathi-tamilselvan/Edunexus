<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * A teacher is in one section at a time. The unique index on (teacher,
 * weekday, period) enforces it; this check names the clash first.
 */
final class TimetableClashes
{
    /**
     * Fails if the teacher already teaches another section in any of the
     * slots.
     *
     * @param  list<array{Weekday, Period}>  $slots
     */
    public static function ensureFree(User $teacher, Section $section, array $slots): void
    {
        if ($slots === []) {
            return;
        }

        $clash = TimetableEntry::query()
            ->with(['section.schoolClass', 'period'])
            ->where('teacher_id', $teacher->id)
            ->where('section_id', '!=', $section->id)
            ->where(function (Builder $query) use ($slots): void {
                foreach ($slots as [$weekday, $period]) {
                    $query->orWhere(fn (Builder $slot) => $slot->where('weekday', $weekday)->where('period_id', $period->id));
                }
            })
            ->first();

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'teacher_id' => "{$teacher->name} already teaches {$clash->section->schoolClass->name} {$clash->section->name} on {$clash->weekday->label()} in {$clash->period->name}.",
            ]);
        }
    }
}
