<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Identity\Models\User;
use App\Support\AcademicYearContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the subjects a teacher teaches, one or more (or none).
 *
 * A subject cannot be taken away while the teacher teaches it to sections
 * in the current year or a later one: those sections would be left with a
 * teacher who no longer teaches it. Earlier years are history and never
 * block.
 */
class SyncTeacherSubjects
{
    public function __construct(private AcademicYearContext $academicYear) {}

    /**
     * @param  list<int>  $subjectIds
     */
    public function handle(User $teacher, array $subjectIds): User
    {
        return DB::transaction(function () use ($teacher, $subjectIds): User {
            $currentYear = $this->academicYear->current();

            $stillTaught = $teacher->subjectTeaching()
                ->whereNotIn('subject_id', $subjectIds)
                ->when($currentYear, fn (Builder $assignments) => $assignments->whereHas(
                    'section.schoolClass.academicYear',
                    fn (Builder $years) => $years->where('starts_on', '>=', $currentYear->starts_on),
                ))
                ->with(['subject', 'section.schoolClass'])
                ->get();

            if ($stillTaught->isNotEmpty()) {
                $subjectId = $stillTaught->first()->subject_id;
                $sections = $stillTaught
                    ->where('subject_id', $subjectId)
                    ->map(fn (SectionSubjectTeacher $assignment): string => "{$assignment->section->schoolClass->name} {$assignment->section->name}");

                throw ValidationException::withMessages(['subject_ids' => sprintf(
                    '%s teaches %s to %s. Give those sections another teacher first.',
                    $teacher->name,
                    $stillTaught->first()->subject->name,
                    $sections->count() > 3 ? $sections->take(3)->implode(', ').' and '.($sections->count() - 3).' more' : $sections->implode(', '),
                )]);
            }

            $teacher->subjects()->sync($subjectIds);
            // Kept for the previous release, which reads one subject per teacher.
            $teacher->forceFill(['subject_id' => $subjectIds[0] ?? null])->save();

            return $teacher->load(['roles', 'subjects']);
        });
    }
}
