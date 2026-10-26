<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Sets who teaches a subject to a section, e.g. Class 1 A Mathematics:
 * Satheesh, or clears it when no teacher is given.
 *
 * Rules: the subject is offered to the section's class, and the teacher
 * is linked to that subject (each teacher teaches one subject).
 */
class AssignSubjectTeacher
{
    public function handle(Section $section, Subject $subject, ?User $teacher): ?SectionSubjectTeacher
    {
        $section->loadMissing('schoolClass');

        if (! $section->schoolClass->subjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => "{$subject->name} is not offered to {$section->schoolClass->name}. Add it to the class's subjects first."]);
        }

        if ($teacher === null) {
            $section->subjectTeachers()->where('subject_id', $subject->id)->delete();

            return null;
        }

        if ($teacher->subject_id !== $subject->id) {
            throw ValidationException::withMessages(['teacher_id' => $teacher->subject_id === null
                ? "{$teacher->name} is not linked to a subject. Link them to {$subject->name} on the Users page first."
                : "{$teacher->name} teaches {$teacher->subject->name}, not {$subject->name}."]);
        }

        return SectionSubjectTeacher::query()->updateOrCreate(
            ['section_id' => $section->id, 'subject_id' => $subject->id],
            ['teacher_id' => $teacher->id],
        );
    }
}
