<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\CompleteFinalClass;
use App\Domain\Academic\Actions\CopyAcademicYearForward;
use App\Domain\Academic\Actions\MakeAcademicYearCurrent;
use App\Domain\Academic\Actions\SetAdmissionsOpen;
use App\Domain\Academic\Actions\YearSetupStatus;
use App\Domain\Academic\Models\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\SetAdmissionsOpenRequest;
use App\Http\Resources\Api\V1\AcademicYearResource;
use Illuminate\Http\JsonResponse;
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
    /**
     * The Year Setup checklist for one year: each step done or what is missing.
     */
    public function setupStatus(AcademicYear $academicYear, YearSetupStatus $yearSetupStatus): JsonResponse
    {
        return response()->json([
            'data' => [
                'academic_year' => ['id' => $academicYear->id, 'name' => $academicYear->name],
                'steps' => $yearSetupStatus->handle($academicYear),
            ],
        ]);
    }

    public function show(AcademicYear $academicYear): AcademicYearResource
    {
        return new AcademicYearResource($academicYear->load([
            'classes.sections' => fn ($sections) => $sections->withCount(['enrollments', 'subjectTeachers'])->with('classTeacher'),
            'classes.subjects',
        ]));
    }

    /**
     * Opens the year after the current one with the current year's
     * structure copied forward.
     */
    public function next(CopyAcademicYearForward $copyAcademicYearForward): JsonResponse
    {
        return (new AcademicYearResource($copyAcademicYearForward->handle()))->response()->setStatusCode(201);
    }

    public function makeCurrent(AcademicYear $academicYear, MakeAcademicYearCurrent $makeCurrent): AcademicYearResource
    {
        return new AcademicYearResource($makeCurrent->handle($academicYear));
    }

    /**
     * What making this year current would do, for the confirm dialog (§3.1). Read only.
     */
    public function makeCurrentPreview(AcademicYear $academicYear, CompleteFinalClass $completeFinalClass): JsonResponse
    {
        $closedYear = $completeFinalClass->closingYearFor($academicYear);

        return response()->json(['data' => $closedYear === null
            ? ['closing_year' => null, 'final_class' => null, 'students_to_leave' => 0]
            : $completeFinalClass->preview($closedYear, $academicYear)]);
    }

    /**
     * Switches admissions on or off for the year (D44).
     */
    public function setAdmissions(SetAdmissionsOpenRequest $request, AcademicYear $academicYear, SetAdmissionsOpen $setAdmissionsOpen): AcademicYearResource
    {
        return new AcademicYearResource($setAdmissionsOpen->handle($academicYear, $request->boolean('open')));
    }
}
