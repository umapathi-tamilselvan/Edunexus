<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives every section of a class the same teacher for a subject, e.g. all
 * of Class 1's Mathematics to Satheesh, replacing the sections' own
 * teachers; with no teacher it clears the subject in every section. Each
 * section keeps its own assignment, so one can be changed afterwards.
 */
class AssignClassSubjectTeacher
{
    public function __construct(private AssignSubjectTeacher $assignSubjectTeacher) {}

    /**
     * @return Collection<int, Section> the class's sections
     */
    public function handle(SchoolClass $schoolClass, Subject $subject, ?User $teacher): Collection
    {
        return DB::transaction(function () use ($schoolClass, $subject, $teacher): Collection {
            $sections = $schoolClass->sections()->get();

            foreach ($sections as $section) {
                $this->assignSubjectTeacher->handle($section->setRelation('schoolClass', $schoolClass), $subject, $teacher);
            }

            return $sections;
        });
    }
}
