<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\Academic\Models\Section;
use App\Domain\Student\Actions\EnrollStudent;
use App\Domain\Student\Actions\UpdateEnrollment;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\EnrollStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateEnrollmentRequest;
use App\Http\Resources\Api\V1\EnrollmentResource;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function store(EnrollStudentRequest $request, Student $student, EnrollStudent $enrollStudent): JsonResponse
    {
        $enrollment = $enrollStudent->handle(
            $student,
            Section::query()->findOrFail($request->validated('section_id')),
            $request->validated('roll_no'),
        );

        return (new EnrollmentResource($enrollment))->response()->setStatusCode(201);
    }

    public function update(UpdateEnrollmentRequest $request, StudentEnrollment $enrollment, UpdateEnrollment $updateEnrollment): EnrollmentResource
    {
        return new EnrollmentResource($updateEnrollment->handle(
            $enrollment,
            $request->has('section_id') ? Section::query()->findOrFail($request->validated('section_id')) : null,
            $request->validated('roll_no'),
            $request->has('roll_no'),
        ));
    }
}
