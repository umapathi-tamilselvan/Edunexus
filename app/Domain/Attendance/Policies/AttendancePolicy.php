<?php

namespace App\Domain\Attendance\Policies;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Support\OrganizationContext;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
{
    public function __construct(private OrganizationContext $organization) {}

    /**
     * Anyone with attendance.edit_late marks any section on any day. With
     * attendance.mark, a section's class teacher and subject teachers mark
     * it within the edit window: the day itself, or the client's
     * attendance.edit_window_days before it.
     */
    public function mark(User $user, Section $section, CarbonInterface $date): Response
    {
        if ($user->can(Permission::AttendanceEditLate->value)) {
            return Response::allow();
        }

        if (! $user->can(Permission::AttendanceMark->value) || ! $this->teaches($user, $section)) {
            return Response::deny('Only this section\'s class teacher and subject teachers mark its attendance.');
        }

        $window = $this->organization->attendanceEditWindowDays();

        if ($date->lt(today()->subDays($window))) {
            return Response::deny($window === 0
                ? 'Attendance can be changed only on the day. Ask an administrator to change an earlier day.'
                : "Attendance can be changed only within {$window} days. Ask an administrator to change an earlier day.");
        }

        return Response::allow();
    }

    private function teaches(User $user, Section $section): bool
    {
        return $section->class_teacher_id === $user->id
            || SectionSubjectTeacher::query()->where('section_id', $section->id)->where('teacher_id', $user->id)->exists();
    }
}
