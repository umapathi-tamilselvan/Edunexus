<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Attendance\Actions\AttendanceScores;
use App\Domain\Examination\Actions\ConsolidateSubjectScores;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SubjectScoreController extends Controller
{
    /**
     * The student's academic score (per subject and overall) and attendance
     * score, out of the client's scale, from all their years. The two are
     * returned side by side and never combined.
     */
    public function __invoke(Student $student, ConsolidateSubjectScores $consolidateSubjectScores, AttendanceScores $attendanceScores): JsonResponse
    {
        Gate::authorize('view', $student);

        $academic = $consolidateSubjectScores->handle($student);
        $attendance = $attendanceScores->combined($student->enrollments()->pluck('id')->all());

        return response()->json([
            'data' => [
                'scale' => $academic['scale'],
                'academic' => [
                    'score' => $academic['overall']['score'] ?? null,
                    'average_percentage' => $academic['overall']['average_percentage'] ?? null,
                    'subjects' => $academic['subjects'],
                ],
                'attendance' => [
                    'score' => $attendance['percentage'] === null ? null : round($attendance['percentage'] / 100 * $academic['scale'], 1),
                    'percentage' => $attendance['percentage'],
                    'days_marked' => $attendance['days_marked'],
                    'days_attended' => $attendance['days_attended'],
                ],
            ],
        ]);
    }
}
