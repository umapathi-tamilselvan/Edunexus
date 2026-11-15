<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets (creates or replaces) one slot of a section's weekly timetable.
 *
 * Without a teacher the slot gets the section's subject teacher for the
 * subject, and follows it when it changes; any other teacher is an
 * override that stays put.
 *
 * Rules: the period belongs to the section's academic year and is not a
 * break, the subject is offered to the section's class, and the teacher is
 * not already teaching another section in that slot.
 */
class SetTimetableSlot
{
    public function handle(Section $section, Weekday $weekday, Period $period, Subject $subject, ?User $teacher): TimetableEntry
    {
        $section->loadMissing('schoolClass');

        if ($period->academic_year_id !== $section->schoolClass->academic_year_id) {
            throw ValidationException::withMessages(['period_id' => 'This period belongs to another academic year.']);
        }

        if ($period->is_break) {
            throw ValidationException::withMessages(['period_id' => "{$period->name} is a break."]);
        }

        if (! $section->schoolClass->subjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => "{$subject->name} is not offered to {$section->schoolClass->name}. Add it to the class's subjects first."]);
        }

        $subjectTeacher = $section->subjectTeachers()->where('subject_id', $subject->id)->with('teacher')->first()?->teacher;
        $isOverride = $teacher !== null && $teacher->id !== $subjectTeacher?->id;
        $teacher ??= $subjectTeacher;

        try {
            return DB::transaction(function () use ($section, $weekday, $period, $subject, $teacher, $isOverride): TimetableEntry {
                if ($teacher !== null) {
                    TimetableClashes::ensureFree($teacher, $section, [[$weekday, $period]]);
                }

                $entry = TimetableEntry::query()->updateOrCreate(
                    ['section_id' => $section->id, 'weekday' => $weekday, 'period_id' => $period->id],
                    ['subject_id' => $subject->id, 'teacher_id' => $teacher?->id, 'is_override' => $isOverride],
                );

                return $entry->load(['period', 'subject', 'teacher']);
            });
        } catch (UniqueConstraintViolationException) {
            // Two people booked the same teacher at the same moment.
            if ($teacher !== null) {
                TimetableClashes::ensureFree($teacher, $section, [[$weekday, $period]]);
            }

            throw ValidationException::withMessages(['teacher_id' => 'This slot was changed by someone else. Reload and try again.']);
        }
    }
}
