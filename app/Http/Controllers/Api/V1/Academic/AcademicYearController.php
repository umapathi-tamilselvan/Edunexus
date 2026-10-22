<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\CreateAcademicYear;
use App\Domain\Academic\Actions\MakeAcademicYearCurrent;
use App\Domain\Academic\Models\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StoreAcademicYearRequest;
use App\Http\Resources\Api\V1\AcademicYearResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

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

    public function store(StoreAcademicYearRequest $request, CreateAcademicYear $createAcademicYear): JsonResponse
    {
        $year = $createAcademicYear->handle(
            name: $request->validated('name'),
            startsOn: Carbon::parse($request->validated('starts_on')),
            endsOn: Carbon::parse($request->validated('ends_on')),
            makeCurrent: $request->boolean('make_current'),
        );

        return (new AcademicYearResource($year))->response()->setStatusCode(201);
    }

    public function update(StoreAcademicYearRequest $request, AcademicYear $academicYear): AcademicYearResource
    {
        $academicYear->update($request->safe()->only(['name', 'starts_on', 'ends_on']));

        return new AcademicYearResource($academicYear);
    }

    public function makeCurrent(AcademicYear $academicYear, MakeAcademicYearCurrent $makeCurrent): AcademicYearResource
    {
        return new AcademicYearResource($makeCurrent->handle($academicYear));
    }
}
