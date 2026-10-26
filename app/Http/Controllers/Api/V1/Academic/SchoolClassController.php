<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\SyncClassSubjects;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StoreSchoolClassRequest;
use App\Http\Requests\Api\V1\Academic\SyncClassSubjectsRequest;
use App\Http\Resources\Api\V1\SchoolClassResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SchoolClassController extends Controller
{
    public function index(AcademicYear $academicYear): AnonymousResourceCollection
    {
        return SchoolClassResource::collection($academicYear->classes()->with(['sections', 'subjects'])->get());
    }

    public function store(StoreSchoolClassRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $class = $academicYear->classes()->create($request->validated());

        return (new SchoolClassResource($class))->response()->setStatusCode(201);
    }

    public function update(StoreSchoolClassRequest $request, SchoolClass $schoolClass): SchoolClassResource
    {
        $schoolClass->update($request->validated());

        return new SchoolClassResource($schoolClass);
    }

    public function destroy(SchoolClass $schoolClass): Response
    {
        if ($schoolClass->sections()->exists()) {
            throw ValidationException::withMessages(['class' => 'Remove this class\'s sections first.']);
        }

        $schoolClass->delete();

        return response()->noContent();
    }

    public function syncSubjects(SyncClassSubjectsRequest $request, SchoolClass $schoolClass, SyncClassSubjects $syncClassSubjects): SchoolClassResource
    {
        return new SchoolClassResource($syncClassSubjects->handle($schoolClass, $request->validated('subject_ids')));
    }
}
