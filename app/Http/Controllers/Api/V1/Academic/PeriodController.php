<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StorePeriodRequest;
use App\Http\Resources\Api\V1\PeriodResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * The bell schedule of an academic year.
 */
class PeriodController extends Controller
{
    public function index(AcademicYear $academicYear): AnonymousResourceCollection
    {
        return PeriodResource::collection($academicYear->periods()->get());
    }

    public function store(StorePeriodRequest $request, AcademicYear $academicYear): JsonResponse
    {
        return (new PeriodResource($academicYear->periods()->create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(StorePeriodRequest $request, Period $period): PeriodResource
    {
        if ($request->boolean('is_break') && $period->entries()->exists()) {
            throw ValidationException::withMessages(['is_break' => 'Clear this period from the timetable before making it a break.']);
        }

        $period->update($request->validated());

        return new PeriodResource($period);
    }

    public function destroy(Period $period): Response
    {
        if ($period->entries()->exists()) {
            throw ValidationException::withMessages(['period' => 'This period is used in the timetable. Clear those slots first.']);
        }

        $period->delete();

        return response()->noContent();
    }
}
