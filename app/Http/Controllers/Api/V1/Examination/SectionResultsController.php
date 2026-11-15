<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Academic\Models\Section;
use App\Domain\Examination\Actions\ClassroomResults;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A classroom's results for its year: academic and attendance scores for
 * every student the user may see, kept apart.
 */
class SectionResultsController extends Controller
{
    public function __invoke(Request $request, Section $section, ClassroomResults $classroomResults): JsonResponse
    {
        return response()->json(['data' => $classroomResults->handle($section, $request->user())]);
    }
}
