<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets who teaches a subject to a section, e.g. Class 1 A Mathematics:
 * Satheesh, or clears it when no teacher is given. The section's timetable
 * slots for the subject follow, except those set by hand to someone else.
 *
 * Rules: the subject is offered to the section's class, the teacher
 * teaches that subject (one of theirs), and the teacher is free in every
 * slot that follows them; otherwise nothing changes.
 */
class AssignSubjectTeacher
{
    public function handle(Section $section, Subject $subject, ?User $teacher): ?SectionSubjectTeacher
    {
        $section->loadMissing('schoolClass');

        if (! $section->schoolClass->subjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => "{$subject->name} is not offered to {$section->schoolClass->name}. Add it to the class's subjects first."]);
        }

        if ($teacher !== null) {
            $taught = $teacher->subjects()->pluck('name', 'subjects.id');

            if (! $taught->has($subject->id)) {
                throw ValidationException::withMessages(['teacher_id' => $taught->isEmpty()
                    ? "{$teacher->name} is not linked to a subject. Link them to {$subject->name} first."
                    : "{$teacher->name} teaches {$taught->join(', ', ' and ')}, not {$subject->name}."]);
            }
        }

        try {
            return DB::transaction(function () use ($section, $subject, $teacher): ?SectionSubjectTeacher {
                $this->moveTimetableSlots($section, $subject, $teacher);

                if ($teacher === null) {
                    $section->subjectTeachers()->where('subject_id', $subject->id)->delete();

                    return null;
                }

                return SectionSubjectTeacher::query()->updateOrCreate(
                    ['section_id' => $section->id, 'subject_id' => $subject->id],
                    ['teacher_id' => $teacher->id],
                );
            });
        } catch (UniqueConstraintViolationException) {
            // Someone booked the teacher, or this subject, at the same moment.
            throw ValidationException::withMessages(['teacher_id' => 'Someone else changed this at the same time. Reload and try again.']);
        }
    }

    /**
     * Slots following the subject teacher get the new one (or none). A slot
     * already set by hand to the new teacher now simply follows them.
     */
    private function moveTimetableSlots(Section $section, Subject $subject, ?User $teacher): void
    {
        $slots = $section->timetableEntries()
            ->where('subject_id', $subject->id)
            ->where(fn ($query) => $query->where('is_override', false)->when($teacher, fn ($query) => $query->orWhere('teacher_id', $teacher->id)))
            ->with('period')
            ->get();

        if ($teacher !== null) {
            TimetableClashes::ensureFree($teacher, $section, $slots->map(fn (TimetableEntry $slot): array => [$slot->weekday, $slot->period])->all());
        }

        TimetableEntry::query()->whereKey($slots->modelKeys())->update(['teacher_id' => $teacher?->id, 'is_override' => false]);
    }
}
