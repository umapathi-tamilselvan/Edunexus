<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Models\Subject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StoreSubjectRequest;
use App\Http\Resources\Api\V1\SubjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SubjectResource::collection(Subject::query()->orderBy('name')->get());
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        return (new SubjectResource(Subject::query()->create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(StoreSubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->validated());

        return new SubjectResource($subject);
    }
}
