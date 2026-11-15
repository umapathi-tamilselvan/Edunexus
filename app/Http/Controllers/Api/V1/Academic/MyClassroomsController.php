<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\MyClassrooms;
use App\Http\Controllers\Controller;
use App\Support\AcademicYearContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in teacher's classrooms and today's due work in the selected year.
 */
class MyClassroomsController extends Controller
{
    public function __invoke(Request $request, AcademicYearContext $academicYear, MyClassrooms $myClassrooms): JsonResponse
    {
        $year = $academicYear->year();

        return response()->json(['data' => $year === null ? null : $myClassrooms->handle($request->user(), $year, today())]);
    }
}
