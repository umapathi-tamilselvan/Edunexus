<?php

namespace App\Domain\Examination\Policies;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class ExamPolicy
{
    /**
     * Anyone with exam.manage enters marks for every section. Others with
     * exam.enter_marks only for a subject they teach to the section.
     */
    public function enterMarks(User $user, Section $section, Subject $subject): bool
    {
        if ($user->can(Permission::ExamManage->value)) {
            return true;
        }

        return $user->can(Permission::ExamEnterMarks->value)
            && SectionSubjectTeacher::query()
                ->where('section_id', $section->id)
                ->where('subject_id', $subject->id)
                ->where('teacher_id', $user->id)
                ->exists();
    }
}
