<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StoreSectionRequest;
use App\Http\Resources\Api\V1\SectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function store(StoreSectionRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $section = $schoolClass->sections()->create($request->validated());

        return (new SectionResource($section->load('classTeacher')))->response()->setStatusCode(201);
    }

    public function update(StoreSectionRequest $request, Section $section): SectionResource
    {
        if ($request->filled('capacity') && $section->enrollments()->count() > $request->integer('capacity')) {
            throw ValidationException::withMessages(['capacity' => 'The capacity cannot be lower than the number of students already enrolled.']);
        }

        $section->update($request->validated());

        return new SectionResource($section->load('classTeacher'));
    }

    public function destroy(Section $section): Response
    {
        if ($section->enrollments()->exists()) {
            throw ValidationException::withMessages(['section' => 'Students are enrolled in this section. Move them before deleting it.']);
        }

        $section->delete();

        return response()->noContent();
    }
}
