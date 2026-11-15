<?php

namespace App\Http\Controllers\Api\V1\Home;

use App\Domain\Academic\Actions\OfficeHome;
use App\Http\Controllers\Controller;
use App\Support\AcademicYearContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The office home for Admin and Super Admin (D43): Year Setup progress,
 * classrooms without a class teacher, and the admissions waiting.
 */
class OfficeHomeController extends Controller
{
    public function __invoke(Request $request, AcademicYearContext $academicYear, OfficeHome $officeHome): JsonResponse
    {
        return response()->json(['data' => $officeHome->handle($request->user(), $academicYear->year())]);
    }
}
