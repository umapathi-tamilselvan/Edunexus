<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\MakeAcademicYearCurrent;
use App\Domain\Academic\Models\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AcademicYearResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Academic years are never created or edited by hand: the first comes with
 * the client and each later one from the year before.
 */
class AcademicYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AcademicYearResource::collection(AcademicYear::query()->orderByDesc('starts_on')->get());
    }

    /**
     * A year with its full structure: classes, sections and subjects, and
     * how many of each section's subjects have a teacher.
     */
    public function show(AcademicYear $academicYear): AcademicYearResource
    {
        return new AcademicYearResource($academicYear->load([
            'classes.sections' => fn ($sections) => $sections->withCount(['enrollments', 'subjectTeachers'])->with('classTeacher'),
            'classes.subjects',
        ]));
    }

    public function makeCurrent(AcademicYear $academicYear, MakeAcademicYearCurrent $makeCurrent): AcademicYearResource
    {
        return new AcademicYearResource($makeCurrent->handle($academicYear));
    }
}
