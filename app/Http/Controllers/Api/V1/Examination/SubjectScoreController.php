<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Examination\Actions\StudentScoreCard;
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
    public function __invoke(Student $student, StudentScoreCard $scoreCard): JsonResponse
    {
        Gate::authorize('view', $student);

        return response()->json(['data' => $scoreCard->handle($student)]);
    }
}
