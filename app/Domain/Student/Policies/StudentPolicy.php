<?php

namespace App\Domain\Student\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::StudentView->value, Permission::StudentViewAssigned->value, Permission::StudentManage->value]);
    }

    /**
     * Teachers with only student.view_assigned see students of the sections
     * they are class teacher of; the same rule as Student::visibleTo().
     */
    public function view(User $user, Student $student): bool
    {
        return Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::StudentManage->value);
    }
}
