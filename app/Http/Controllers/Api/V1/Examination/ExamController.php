<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Actions\SetExamMaxMarks;
use App\Domain\Examination\Actions\UpdateExam;
use App\Domain\Examination\Models\Exam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Examination\SetExamMaxMarksRequest;
use App\Http\Requests\Api\V1\Examination\StoreExamRequest;
use App\Http\Resources\Api\V1\ExamResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function index(AcademicYear $academicYear): AnonymousResourceCollection
    {
        return ExamResource::collection($academicYear->exams()->withExists('marks')->get());
    }

    public function store(StoreExamRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $exam = $academicYear->exams()->create($request->validated());

        return (new ExamResource($exam->load('academicYear')->loadExists('marks')))->response()->setStatusCode(201);
    }

    public function show(Exam $exam): ExamResource
    {
        return new ExamResource($exam->load('academicYear')->loadExists('marks'));
    }

    public function update(StoreExamRequest $request, Exam $exam, UpdateExam $updateExam): ExamResource
    {
        return new ExamResource($updateExam->handle($exam, $request->validated())->load('academicYear')->loadExists('marks'));
    }

    public function destroy(Exam $exam): Response
    {
        if ($exam->marks()->exists()) {
            throw ValidationException::withMessages(['exam' => 'Marks have been entered for this exam, so it cannot be deleted.']);
        }

        $exam->delete();

        return response()->noContent();
    }

    /**
     * Sets the maximum marks of one class's subject in the exam.
     */
    public function setMaxMarks(SetExamMaxMarksRequest $request, Exam $exam, SchoolClass $schoolClass, Subject $subject, SetExamMaxMarks $setExamMaxMarks): JsonResponse
    {
        return response()->json([
            'data' => ['max_marks' => $setExamMaxMarks->handle($exam, $schoolClass, $subject, $request->integer('max_marks'))],
        ]);
    }
}
