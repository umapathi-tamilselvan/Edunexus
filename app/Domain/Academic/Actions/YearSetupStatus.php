<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Holiday;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\TimetableEntry;
use App\Support\OrganizationContext;

/**
 * The Year Setup checklist: each step is done or says what is missing. It
 * reads existing data only and never blocks anything. Teaching days are the
 * six weekdays a timetable has (Monday to Saturday).
 */
class YearSetupStatus
{
    public function __construct(private OrganizationContext $organization) {}

    /**
     * @return list<array{key: string, label: string, done: bool, detail: string, optional: bool}>
     */
    public function handle(AcademicYear $year): array
    {
        $classes = SchoolClass::query()->where('academic_year_id', $year->id)->withCount(['sections', 'subjects'])->get();
        $sections = Section::query()->whereIn('class_id', $classes->pluck('id'))->get(['id', 'class_id', 'class_teacher_id']);
        $teachingPeriods = Period::query()->where('academic_year_id', $year->id)->where('is_break', false)->get(['id', 'session']);
        $teachingPeriodIds = $teachingPeriods->pluck('id');
        $mode = (string) $this->organization->settingOrDefault('attendance.mode');

        $classesWithoutSections = $classes->where('sections_count', 0)->count();
        $classesWithoutSubjects = $classes->where('subjects_count', 0)->count();
        $sectionsWithoutClassTeacher = $sections->whereNull('class_teacher_id')->count();

        $subjectSlotsNeeded = $classes->sum(fn (SchoolClass $schoolClass): int => $schoolClass->sections_count * $schoolClass->subjects_count);
        $subjectSlotsFilled = SectionSubjectTeacher::query()->whereIn('section_id', $sections->pluck('id'))->count();

        $timetableSlotsNeeded = $sections->count() * 6 * $teachingPeriodIds->count();
        $timetableSlotsFilled = TimetableEntry::query()
            ->whereIn('section_id', $sections->pluck('id'))
            ->whereIn('period_id', $teachingPeriodIds)
            ->count();

        $holidays = Holiday::query()->where('academic_year_id', $year->id)->count();

        return [
            [
                'key' => 'classes',
                'label' => 'Classes and sections',
                'done' => $classes->isNotEmpty() && $classesWithoutSections === 0,
                'detail' => $classes->isEmpty() ? 'No classes yet.' : ($classesWithoutSections === 0 ? "{$classes->count()} classes, each with sections." : "{$classesWithoutSections} classes have no sections."),
            ],
            [
                'key' => 'subjects',
                'label' => 'Subjects per class',
                'done' => $classes->isNotEmpty() && $classesWithoutSubjects === 0,
                'detail' => $classes->isEmpty() ? 'No classes yet.' : ($classesWithoutSubjects === 0 ? 'Every class has subjects.' : "{$classesWithoutSubjects} classes have no subjects."),
            ],
            [
                'key' => 'class_teachers',
                'label' => 'Class teachers',
                'done' => $sections->isNotEmpty() && $sectionsWithoutClassTeacher === 0,
                'detail' => $sections->isEmpty() ? 'No sections yet.' : ($sectionsWithoutClassTeacher === 0 ? 'Every section has a class teacher.' : "{$sectionsWithoutClassTeacher} sections have no class teacher."),
            ],
            [
                'key' => 'subject_teachers',
                'label' => 'Subject teachers',
                'done' => $subjectSlotsNeeded > 0 && $subjectSlotsFilled >= $subjectSlotsNeeded,
                'detail' => $subjectSlotsNeeded === 0 ? 'No subjects offered yet.' : "{$subjectSlotsFilled} of {$subjectSlotsNeeded} section subjects have a teacher.",
            ],
            [
                'key' => 'bell_schedule',
                'label' => 'Bell schedule',
                'done' => $teachingPeriodIds->isNotEmpty() && ($mode !== 'session' || $teachingPeriods->whereNotNull('session')->isNotEmpty()),
                'detail' => match (true) {
                    $teachingPeriodIds->isEmpty() => 'No teaching periods yet.',
                    $mode === 'session' && $teachingPeriods->whereNotNull('session')->isEmpty() => 'Set each period to morning or afternoon. Attendance is taken by session.',
                    default => "{$teachingPeriodIds->count()} teaching periods a day.",
                },
            ],
            [
                'key' => 'timetable',
                'label' => 'Timetable',
                'done' => $timetableSlotsNeeded > 0 && $timetableSlotsFilled >= $timetableSlotsNeeded,
                'detail' => $timetableSlotsNeeded === 0 ? 'Needs sections and teaching periods first.' : "{$timetableSlotsFilled} of {$timetableSlotsNeeded} slots are filled.",
                'optional' => $mode !== 'period',
            ],
            [
                'key' => 'holidays',
                'label' => 'Holidays',
                'done' => $holidays > 0,
                'detail' => $holidays > 0 ? "{$holidays} holidays added." : 'No holidays added yet.',
            ],
            [
                'key' => 'admissions',
                'label' => 'Admissions open',
                'done' => $year->is_current,
                'detail' => $year->is_current ? 'This is the current year.' : 'Make this the current year when it starts.',
            ],
        ];
    }
}
