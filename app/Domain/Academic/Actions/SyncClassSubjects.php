<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use Illuminate\Support\Facades\DB;

/**
 * Sets the subjects a class studies. A subject taken off the class also
 * loses its subject teachers in the class's sections.
 */
class SyncClassSubjects
{
    /**
     * @param  list<int>  $subjectIds
     */
    public function handle(SchoolClass $schoolClass, array $subjectIds): SchoolClass
    {
        return DB::transaction(function () use ($schoolClass, $subjectIds): SchoolClass {
            $schoolClass->subjects()->sync($subjectIds);

            SectionSubjectTeacher::query()
                ->whereIn('section_id', $schoolClass->sections()->reorder()->select('id'))
                ->whereNotIn('subject_id', $subjectIds)
                ->delete();

            return $schoolClass->load('subjects');
        });
    }
}
