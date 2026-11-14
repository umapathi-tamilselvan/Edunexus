<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Academic\Models\Section;
use App\Domain\Attendance\Actions\RecordAttendance;
use App\Domain\Attendance\Actions\SummarizeAttendance;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\RecordAttendanceRequest;
use App\Support\AcademicYearContext;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Attendance sheets: one section, one day.
 */
class AttendanceController extends Controller
{
    /**
     * The sheet: the section's students the user may see, in roll order,
     * with the day's status of each, a summary, and whether the user may
     * mark it (or why not).
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        $date = Carbon::parse($request->validate(['date' => ['required', 'date_format:Y-m-d']])['date']);
        $section->load('schoolClass.academicYear');
        $day = $date->toDateString();
        $todays = fn ($records) => $records->where('date', $day)->where('period', AttendanceRecord::DAY);

        // Students who have left still show on days they were marked.
        $enrollments = $section->enrollments()
            ->where(fn (Builder $enrollments) => $enrollments->where('status', EnrollmentStatus::Active)->orWhereHas('attendance', $todays))
            ->whereHas('student', fn (Builder $students) => $students->visibleTo($request->user(), $section->schoolClass->academic_year_id))
            ->with(['student:id,name,admission_no', 'attendance' => $todays])
            ->orderBy('roll_no')
            ->get();

        $closed = RecordAttendance::closedReason($section, $date);
        $access = Gate::inspect('mark', [AttendanceRecord::class, $section, $date]);
        $summary = [...array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0), 'unmarked' => 0];

        foreach ($enrollments as $enrollment) {
            $summary[$enrollment->attendance->first()?->status->value ?? 'unmarked']++;
        }

        return response()->json([
            'data' => [
                'date' => $day,
                'section' => ['id' => $section->id, 'name' => $section->name, 'class' => ['id' => $section->schoolClass->id, 'name' => $section->schoolClass->name]],
                'can_mark' => $closed === null && $access->allowed(),
                'closed_reason' => $closed ?? ($access->allowed() ? null : $access->message()),
                'summary' => $summary,
                'students' => $enrollments->map(fn (StudentEnrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'roll_no' => $enrollment->roll_no,
                    'student' => ['id' => $enrollment->student->id, 'name' => $enrollment->student->name, 'admission_no' => $enrollment->student->admission_no],
                    'status' => $enrollment->attendance->first()?->status->value,
                    'remark' => $enrollment->attendance->first()?->remark,
                ]),
            ],
        ]);
    }

    /**
     * Every section of the selected year on one day: how many students are
     * enrolled and how many have a mark, so a page can list the sections not
     * yet taken. One query, whatever the number of sections.
     */
    public function daySummary(Request $request, AcademicYearContext $academicYear): JsonResponse
    {
        $day = Carbon::parse($request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'])->toDateString();

        $sections = Section::query()
            ->whereHas('schoolClass', fn (Builder $classes) => $classes->where('academic_year_id', $academicYear->id()))
            ->with('schoolClass:id,name')
            ->withCount([
                'enrollments as enrolled_count' => fn (Builder $enrollments) => $enrollments->where('status', EnrollmentStatus::Active),
                'enrollments as marked_count' => fn (Builder $enrollments) => $enrollments->whereHas('attendance', fn (Builder $records) => $records->where('date', $day)->where('period', AttendanceRecord::DAY)),
            ])
            ->orderBy('class_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'date' => $day,
                'sections' => $sections->map(fn (Section $section): array => [
                    'id' => $section->id,
                    'label' => "{$section->schoolClass->name} {$section->name}",
                    'enrolled' => $section->enrolled_count,
                    'marked' => $section->marked_count,
                    'taken' => $section->marked_count > 0,
                ])->values(),
            ],
        ]);
    }

    public function update(RecordAttendanceRequest $request, Section $section, RecordAttendance $recordAttendance): JsonResponse
    {
        $date = Carbon::parse($request->validated('date'));

        Gate::authorize('mark', [AttendanceRecord::class, $section, $date]);

        return response()->json(['data' => $recordAttendance->handle($section, $date, $request->validated('entries'), $request->user())]);
    }

    /**
     * A student's attendance in one year: the academic_year_id filter, else
     * the request's selected year.
     */
    public function student(Request $request, Student $student, AcademicYearContext $academicYear, SummarizeAttendance $summarizeAttendance, OrganizationContext $organization): JsonResponse
    {
        Gate::authorize('view', $student);

        $yearId = $request->validate(['academic_year_id' => ['nullable', 'integer']])['academic_year_id'] ?? $academicYear->id();
        $enrollment = $student->enrollments()->with(['academicYear', 'section.schoolClass'])->where('academic_year_id', $yearId)->first();

        $summary = $enrollment === null ? null : $summarizeAttendance->handle($enrollment);

        return response()->json([
            'data' => $enrollment === null ? null : [
                'academic_year' => ['id' => $enrollment->academicYear->id, 'name' => $enrollment->academicYear->name],
                'section' => ['id' => $enrollment->section->id, 'name' => $enrollment->section->name, 'class' => $enrollment->section->schoolClass->name],
                ...$summary,
                'at_risk' => $summary['percentage'] !== null && $summary['percentage'] < (int) $organization->settingOrDefault('insights.at_risk.attendance_below'),
            ],
        ]);
    }
}
