<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Links a teacher to the one subject they teach, or unlinks them.
 *
 * A teacher still assigned to sections for their current subject cannot
 * move to another subject: those sections would be left with a teacher of
 * the wrong subject.
 */
class SetTeacherSubject
{
    public function handle(User $user, ?Subject $subject): User
    {
        $assignments = $user->subjectTeaching()
            ->when($subject, fn ($query) => $query->where('subject_id', '!=', $subject->id))
            ->with(['subject', 'section.schoolClass'])
            ->get();

        if ($assignments->isNotEmpty()) {
            $sections = $assignments->map(fn (SectionSubjectTeacher $assignment): string => "{$assignment->section->schoolClass->name} {$assignment->section->name}");

            throw ValidationException::withMessages(['subject_id' => sprintf(
                '%s teaches %s to %s. Give those sections another teacher first.',
                $user->name,
                $assignments->first()->subject->name,
                $sections->count() > 3 ? $sections->take(3)->implode(', ').' and '.($sections->count() - 3).' more' : $sections->implode(', '),
            )]);
        }

        $user->subject()->associate($subject)->save();

        return $user->load(['roles', 'subject']);
    }
}
