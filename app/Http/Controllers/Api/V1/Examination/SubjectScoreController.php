<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Examination\Actions\ConsolidateSubjectScores;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SubjectScoreController extends Controller
{
    /**
     * The student's score out of 5 in each subject, from all their exams.
     */
    public function __invoke(Student $student, ConsolidateSubjectScores $consolidateSubjectScores): JsonResponse
    {
        Gate::authorize('view', $student);

        return response()->json(['data' => $consolidateSubjectScores->handle($student)]);
    }
}
