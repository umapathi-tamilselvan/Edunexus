<?php

namespace App\Domain\Attendance\Policies;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Attendance\Models\AttendanceRecord;
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
     * attendance.mark, who may mark depends on the attendance mode:
     *
     * - daily: the section's class teacher or a subject teacher of it (D41);
     * - session: the class teacher, or the teacher of the session's first period;
     * - period: the teacher of that period on the timetable.
     *
     * Within the edit window: the day itself, or the client's
     * attendance.edit_window_days before it.
     */
    public function mark(User $user, Section $section, CarbonInterface $date, int $period = AttendanceRecord::DAY): Response
    {
        if ($user->can(Permission::AttendanceEditLate->value)) {
            return Response::allow();
        }

        if (! $user->can(Permission::AttendanceMark->value) || ! $this->mayMark($user, $section, $date, $period)) {
            return Response::deny($this->whoMayMark());
        }

        $window = $this->organization->attendanceEditWindowDays();

        if ($date->lt(today()->subDays($window))) {
            return Response::deny($window === 0
                ? 'Attendance can be changed only on the day. Ask an administrator to change an earlier day.'
                : "Attendance can be changed only within {$window} days. Ask an administrator to change an earlier day.");
        }

        return Response::allow();
    }

    private function mayMark(User $user, Section $section, CarbonInterface $date, int $period): bool
    {
        $isClassTeacher = $section->class_teacher_id === $user->id;

        return match ($this->organization->settingOrDefault('attendance.mode')) {
            'session' => $isClassTeacher || $this->slotTeacherId($section, $date, $period) === $user->id,
            'period' => $this->slotTeacherId($section, $date, $period) === $user->id,
            default => $isClassTeacher || $this->teachesSection($user, $section),
        };
    }

    /**
     * Whether the user is a subject teacher of the section (D41).
     */
    private function teachesSection(User $user, Section $section): bool
    {
        return SectionSubjectTeacher::query()->where('section_id', $section->id)->where('teacher_id', $user->id)->exists();
    }

    /**
     * The teacher of the section's timetable slot for the period on the day.
     */
    private function slotTeacherId(Section $section, CarbonInterface $date, int $period): ?int
    {
        if ($period === AttendanceRecord::DAY) {
            return null;
        }

        return TimetableEntry::query()
            ->where('section_id', $section->id)
            ->where('weekday', $date->dayOfWeekIso)
            ->where('period_id', $period)
            ->value('teacher_id');
    }

    private function whoMayMark(): string
    {
        return match ($this->organization->settingOrDefault('attendance.mode')) {
            'session' => 'Only the class teacher, or the teacher of this session\'s first period, marks it.',
            'period' => 'Only the teacher of this period on the timetable marks it.',
            default => 'Only this section\'s class teacher and subject teachers mark its attendance.',
        };
    }
}
