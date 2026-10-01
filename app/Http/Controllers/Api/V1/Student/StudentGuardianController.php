<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\Student\Actions\LinkGuardian;
use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\LinkGuardianRequest;
use App\Http\Resources\Api\V1\GuardianResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentGuardianController extends Controller
{
    public function store(LinkGuardianRequest $request, Student $student, LinkGuardian $linkGuardian): JsonResponse
    {
        $guardian = $linkGuardian->handle(
            $student,
            $request->safe()->only(['name', 'phone', 'email']),
            GuardianRelation::from($request->validated('relation')),
            $request->boolean('is_primary'),
        );

        return (new GuardianResource($guardian))->response()->setStatusCode(201);
    }

    /**
     * Unlinks a guardian. A student always keeps at least one guardian; if
     * the primary one is removed, the next guardian becomes primary.
     */
    public function destroy(Student $student, Guardian $guardian): Response
    {
        DB::transaction(function () use ($student, $guardian): void {
            $linked = $student->guardians()->lockForUpdate()->get();
            $link = $linked->firstWhere('id', $guardian->id);

            if ($link === null) {
                abort(404);
            }

            if ($linked->count() === 1) {
                throw ValidationException::withMessages(['guardian' => 'A student must keep at least one guardian.']);
            }

            $student->guardians()->detach($guardian->id);

            if ($link->pivot->is_primary) {
                $student->guardians()->updateExistingPivot($linked->firstWhere('id', '!=', $guardian->id)->id, ['is_primary' => true]);
            }
        });

        return response()->noContent();
    }
}
