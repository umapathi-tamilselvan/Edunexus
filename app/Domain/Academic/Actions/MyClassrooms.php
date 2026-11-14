<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Attendance\Actions\AttendancePeriods;
use App\Domain\Attendance\Actions\RecordAttendance;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMarkSheet;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\StudentEnrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The teacher's classrooms and what is due in them today: the classrooms
 * where they are class teacher (first), then those where they teach; the
 * attendance sheets not yet taken today, by the client's attendance mode;
 * and the mark sheets not yet entered for exams not yet published.
 */
class MyClassrooms
{
    /**
     * Exams whose marks are due: those ending within this many days, or with
     * no end date (home.marks_due_window_days in §6).
     */
    public const MARKS_DUE_WINDOW_DAYS = 14;

    public function __construct(private AttendancePeriods $attendancePeriods) {}

    /**
     * @return array{date: string, mode: string, classrooms: array{as_class_teacher: list<array<string, mixed>>, teaching: list<array<string, mixed>>}, attendance_due: list<array<string, mixed>>, marks_due: list<array<string, mixed>>}
     */
    public function handle(User $user, AcademicYear $year, CarbonInterface $today): array
    {
        $sections = Section::query()
            ->taughtBy($user, $year->id)
            ->with(['schoolClass:id,name,sort_order', 'classTeacher:id,name'])
            ->withCount(['enrollments as enrolled_count' => fn ($enrollments) => $enrollments->where('status', EnrollmentStatus::Active)])
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d%s%s', $section->schoolClass->sort_order, $section->schoolClass->name, $section->name))
            ->values();

        return [
            'date' => $today->toDateString(),
            'mode' => $this->attendancePeriods->mode(),
            'classrooms' => $this->classrooms($user, $sections),
            'attendance_due' => $user->can(Permission::AttendanceView->value) ? $this->attendanceDue($user, $sections, $today) : [],
            'marks_due' => $user->can(Permission::ExamEnterMarks->value) ? $this->marksDue($user, $year, $sections, $today) : [],
        ];
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @return array{as_class_teacher: list<array<string, mixed>>, teaching: list<array<string, mixed>>}
     */
    private function classrooms(User $user, Collection $sections): array
    {
        $subjects = SectionSubjectTeacher::query()
            ->where('teacher_id', $user->id)
            ->whereIn('section_id', $sections->modelKeys())
            ->with('subject:id,name')
            ->get()
            ->groupBy('section_id')
            ->map(fn (Collection $rows): array => $rows->map(fn (SectionSubjectTeacher $row): string => $row->subject->name)->sort()->values()->all());

        $card = fn (Section $section): array => [
            'id' => $section->id,
            'label' => $this->label($section),
            'enrolled' => $section->enrolled_count,
            'subjects' => $subjects->get($section->id, []),
        ];

        return [
            'as_class_teacher' => $sections->filter(fn (Section $section): bool => $section->class_teacher_id === $user->id)->map($card)->values()->all(),
            'teaching' => $sections->filter(fn (Section $section): bool => $section->class_teacher_id !== $user->id)->map($card)->values()->all(),
        ];
    }

    /**
     * Sheets for today that the user may mark and that have no record yet:
     * the whole day in daily mode, each session or each period otherwise.
     *
     * @param  Collection<int, Section>  $sections
     * @return list<array{section: array{id: int, label: string}, period: array{id: int, label: string}|null}>
     */
    private function attendanceDue(User $user, Collection $sections, CarbonInterface $today): array
    {
        $open = $sections->filter(fn (Section $section): bool => RecordAttendance::closedReason($section, $today) === null);

        $candidates = [];

        foreach ($open as $section) {
            if ($this->attendancePeriods->mode() === 'daily') {
                $candidates[] = [$section, AttendanceRecord::DAY, null];

                continue;
            }

            foreach ($this->attendancePeriods->options($section, $today) as $option) {
                $candidates[] = [$section, $option['id'], $option['label']];
            }
        }

        if ($candidates === []) {
            return [];
        }

        $sectionOf = StudentEnrollment::query()
            ->whereIn('section_id', $open->modelKeys())
            ->pluck('section_id', 'id');

        $taken = AttendanceRecord::query()
            ->whereIn('enrollment_id', $sectionOf->keys())
            ->where('date', $today->toDateString())
            ->get(['enrollment_id', 'period'])
            ->map(fn (AttendanceRecord $record): string => "{$sectionOf->get($record->enrollment_id)}-{$record->period}")
            ->flip();

        $due = [];

        foreach ($candidates as [$section, $period, $label]) {
            if ($taken->has("{$section->id}-{$period}") || ! $user->can('mark', [AttendanceRecord::class, $section, $today, $period])) {
                continue;
            }

            $due[] = [
                'section' => ['id' => $section->id, 'label' => $this->label($section)],
                'period' => $label === null ? null : ['id' => $period, 'label' => $label],
            ];
        }

        usort($due, fn (array $a, array $b): int => strcmp($a['section']['label'].($a['period']['label'] ?? ''), $b['section']['label'].($b['period']['label'] ?? '')));

        return $due;
    }

    /**
     * Mark sheets the user teaches that are not entered yet, for exams not
     * yet published that end within the window (or have no end date).
     *
     * @param  Collection<int, Section>  $sections
     * @return list<array{exam: array{id: int, name: string}, section: array{id: int, label: string}, subject: array{id: int, name: string}}>
     */
    private function marksDue(User $user, AcademicYear $year, Collection $sections, CarbonInterface $today): array
    {
        $assignments = SectionSubjectTeacher::query()
            ->where('teacher_id', $user->id)
            ->whereIn('section_id', $sections->modelKeys())
            ->with('subject:id,name')
            ->get();

        $exams = Exam::query()
            ->where('academic_year_id', $year->id)
            ->whereNull('published_at')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '<=', $today->copy()->addDays(self::MARKS_DUE_WINDOW_DAYS)->toDateString()))
            ->get(['id', 'name']);

        if ($assignments->isEmpty() || $exams->isEmpty()) {
            return [];
        }

        $entered = ExamMarkSheet::query()
            ->whereIn('exam_id', $exams->modelKeys())
            ->whereIn('section_id', $sections->modelKeys())
            ->get(['exam_id', 'section_id', 'subject_id'])
            ->map(fn (ExamMarkSheet $sheet): string => "{$sheet->exam_id}-{$sheet->section_id}-{$sheet->subject_id}")
            ->flip();

        $sectionById = $sections->keyBy('id');
        $due = [];

        foreach ($exams as $exam) {
            foreach ($assignments as $assignment) {
                if ($entered->has("{$exam->id}-{$assignment->section_id}-{$assignment->subject_id}")) {
                    continue;
                }

                $section = $sectionById->get($assignment->section_id);
                $due[] = [
                    'exam' => ['id' => $exam->id, 'name' => $exam->name],
                    'section' => ['id' => $section->id, 'label' => $this->label($section)],
                    'subject' => ['id' => $assignment->subject_id, 'name' => $assignment->subject->name],
                ];
            }
        }

        usort($due, fn (array $a, array $b): int => strcmp($a['section']['label'].$a['subject']['name'].$a['exam']['name'], $b['section']['label'].$b['subject']['name'].$b['exam']['name']));

        return $due;
    }

    private function label(Section $section): string
    {
        return "{$section->schoolClass->name} {$section->name}";
    }
}
