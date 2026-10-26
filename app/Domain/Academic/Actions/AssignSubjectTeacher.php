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
 * teaches that subject (one of theirs).
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

        $taught = $teacher->subjects()->pluck('name', 'subjects.id');

        if (! $taught->has($subject->id)) {
            throw ValidationException::withMessages(['teacher_id' => $taught->isEmpty()
                ? "{$teacher->name} is not linked to a subject. Link them to {$subject->name} first."
                : "{$teacher->name} teaches {$taught->join(', ', ' and ')}, not {$subject->name}."]);
        }

        return SectionSubjectTeacher::query()->updateOrCreate(
            ['section_id' => $section->id, 'subject_id' => $subject->id],
            ['teacher_id' => $teacher->id],
        );
    }
}
