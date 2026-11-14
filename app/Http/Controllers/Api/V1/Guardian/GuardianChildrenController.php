<?php

namespace App\Http\Controllers\Api\V1\Guardian;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Attendance\Actions\AttendancePeriods;
use App\Domain\Attendance\Actions\SummarizeAttendance;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Examination\Actions\StudentScoreCard;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * A guardian's view of their linked children: attendance, published exam
 * marks and scores. Each child is checked by GuardianPolicy, and the year is
 * the current one unless another is asked for.
 */
class GuardianChildrenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::query()
            ->whereHas('guardians', fn (Builder $guardians) => $guardians->where('user_id', $request->user()->id))
            ->with(['enrollments' => fn ($enrollments) => $enrollments
                ->whereHas('academicYear', fn (Builder $years) => $years->where('is_current', true))
                ->with(['section.schoolClass'])])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $students->map(function (Student $student): array {
                $enrollment = $student->enrollments->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'admission_no' => $student->admission_no,
                    'current' => $enrollment === null ? null : [
                        'class' => $enrollment->section->schoolClass->name,
                        'section' => $enrollment->section->name,
                        'roll_no' => $enrollment->roll_no,
                    ],
                ];
            })->values(),
        ]);
    }

    /**
     * The summary for the year, today's status (for the whole day, or each
     * session or period by the school's mode), and the chosen month.
     */
    public function attendance(Request $request, Student $student, AttendancePeriods $attendancePeriods, SummarizeAttendance $summarize): JsonResponse
    {
        Gate::authorize('child', [Guardian::class, $student]);

        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $enrollment = $this->enrollment($student, $validated['academic_year_id'] ?? null);

        if ($enrollment === null) {
            return response()->json(['data' => null]);
        }

        $enrollment->section->loadMissing('schoolClass');
        $today = today();
        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? $today->format('Y-m'))->startOfMonth();
        $todaysRecords = $enrollment->attendance()->where('date', $today->toDateString())->get()->keyBy('period');

        $todaysEntries = $attendancePeriods->mode() === 'daily'
            ? [['period_id' => null, 'label' => 'Whole day', 'status' => $todaysRecords->get(AttendanceRecord::DAY)?->status?->value]]
            : array_map(fn (array $option): array => [
                'period_id' => $option['id'],
                'label' => $option['label'],
                'starts_at' => $option['starts_at'],
                'ends_at' => $option['ends_at'],
                'status' => $todaysRecords->get($option['id'])?->status?->value,
            ], $attendancePeriods->options($enrollment->section, $today));

        $monthEntries = $enrollment->attendance()
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->orderBy('period')
            ->get(['date', 'period', 'status'])
            ->map(fn (AttendanceRecord $record): array => [
                'date' => $record->date->toDateString(),
                'period_id' => $record->period === AttendanceRecord::DAY ? null : $record->period,
                'status' => $record->status->value,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'academic_year' => ['id' => $enrollment->academicYear->id, 'name' => $enrollment->academicYear->name],
                'section' => ['id' => $enrollment->section->id, 'name' => $enrollment->section->name, 'class' => $enrollment->section->schoolClass->name],
                'mode' => $attendancePeriods->mode(),
                'summary' => $summarize->handle($enrollment),
                'today' => ['date' => $today->toDateString(), 'entries' => $todaysEntries],
                'month' => ['month' => $month->format('Y-m'), 'entries' => $monthEntries],
            ],
        ]);
    }

    /**
     * The child's marks in exams whose marks are published, by exam and subject.
     */
    public function marks(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('child', [Guardian::class, $student]);

        $enrollment = $this->enrollment($student, $request->validate(['academic_year_id' => ['nullable', 'integer']])['academic_year_id'] ?? null);

        if ($enrollment === null) {
            return response()->json(['data' => []]);
        }

        $enrollment->section->loadMissing('schoolClass');

        $exams = Exam::query()
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->whereNotNull('published_at')
            ->orderBy('starts_on')
            ->orderBy('name')
            ->get(['id', 'name', 'max_marks', 'published_at']);

        $marks = ExamMark::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('exam_id', $exams->modelKeys())
            ->with('subject:id,name')
            ->get();

        $papers = ExamPaper::query()
            ->where('class_id', $enrollment->section->class_id)
            ->whereIn('exam_id', $exams->modelKeys())
            ->get()
            ->keyBy(fn (ExamPaper $paper): string => "{$paper->exam_id}-{$paper->subject_id}");

        return response()->json([
            'data' => $exams
                ->map(fn (Exam $exam): array => [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'published_at' => $exam->published_at->toIso8601String(),
                    'subjects' => $marks
                        ->where('exam_id', $exam->id)
                        ->sortBy(fn (ExamMark $mark): string => $mark->subject->name)
                        ->map(fn (ExamMark $mark): array => [
                            'subject' => ['id' => $mark->subject_id, 'name' => $mark->subject->name],
                            'marks' => $mark->marks,
                            'max_marks' => $papers->get("{$exam->id}-{$mark->subject_id}")?->max_marks ?? $exam->max_marks,
                            'is_absent' => $mark->is_absent,
                        ])
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $exam): bool => $exam['subjects'] !== [])
                ->values()
                ->all(),
        ]);
    }

    /**
     * The child's academic and attendance scores, out of the client's scale,
     * counting published exams only.
     */
    public function scores(Student $student, StudentScoreCard $scoreCard): JsonResponse
    {
        Gate::authorize('child', [Guardian::class, $student]);

        return response()->json(['data' => $scoreCard->handle($student, publishedOnly: true)]);
    }

    private function enrollment(Student $student, ?int $academicYearId): ?StudentEnrollment
    {
        $yearId = $academicYearId ?? AcademicYear::query()->where('is_current', true)->value('id');

        return $student->enrollments()
            ->where('academic_year_id', $yearId)
            ->with(['academicYear', 'section.schoolClass'])
            ->first();
    }
}
