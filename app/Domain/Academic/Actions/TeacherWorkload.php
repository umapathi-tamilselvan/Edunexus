<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * How much each teacher teaches in a year: their timetable periods on each
 * weekday and in the week, and how many section subjects they teach.
 * Counted in the database, so the cost doesn't grow with the timetable.
 */
class TeacherWorkload
{
    /**
     * @return array{periods_per_day: int, teachers: list<array{id: int, name: string, subjects: list<array{id: int, name: string}>, section_subjects: int, periods: array<int, int>, total: int}>} periods by weekday (1 = Monday)
     */
    public function handle(AcademicYear $year): array
    {
        $periods = TimetableEntry::query()
            ->toBase()
            ->join('periods', 'periods.id', '=', 'timetable_entries.period_id')
            ->where('periods.academic_year_id', $year->id)
            ->whereNotNull('timetable_entries.teacher_id')
            ->groupBy('timetable_entries.teacher_id', 'timetable_entries.weekday')
            ->selectRaw('timetable_entries.teacher_id, timetable_entries.weekday, COUNT(*) AS periods')
            ->get()
            ->groupBy('teacher_id');

        $sectionSubjects = SectionSubjectTeacher::query()
            ->toBase()
            ->join('sections', 'sections.id', '=', 'section_subject_teachers.section_id')
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('classes.academic_year_id', $year->id)
            ->groupBy('section_subject_teachers.teacher_id')
            ->selectRaw('section_subject_teachers.teacher_id, COUNT(*) AS section_subjects')
            ->get()
            ->pluck('section_subjects', 'teacher_id');

        $teachers = User::query()
            ->where(fn (Builder $users) => $users
                ->role(Role::Teacher->value)
                ->orWhereKey([...$periods->keys(), ...$sectionSubjects->keys()]))
            ->with('subjects:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'periods_per_day' => $year->periods()->where('is_break', false)->count(),
            'teachers' => $teachers->map(function (User $teacher) use ($periods, $sectionSubjects): array {
                // Every weekday, so a teacher without periods has the same shape.
                $perWeekday = array_fill_keys(array_column(Weekday::cases(), 'value'), 0);

                /** @var object{weekday: int, periods: int} $day */
                foreach ($periods->get($teacher->id, []) as $day) {
                    $perWeekday[(int) $day->weekday] = (int) $day->periods;
                }

                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'subjects' => $teacher->subjects->map(fn ($subject): array => ['id' => $subject->id, 'name' => $subject->name])->values()->all(),
                    'section_subjects' => (int) $sectionSubjects->get($teacher->id, 0),
                    'periods' => $perWeekday,
                    'total' => array_sum($perWeekday),
                ];
            })->all(),
        ];
    }
}
