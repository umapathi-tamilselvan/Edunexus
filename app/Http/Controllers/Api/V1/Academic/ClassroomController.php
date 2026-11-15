<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\CreateClassrooms;
use App\Domain\Academic\Actions\SaveTeacherGrid;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Student\Actions\DistributeStudents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\CreateClassroomsRequest;
use App\Http\Requests\Api\V1\Academic\SaveTeacherGridRequest;
use App\Http\Requests\Api\V1\Student\DistributeStudentsRequest;
use Illuminate\Http\JsonResponse;

/**
 * A class's classrooms (its sections): creating them in one go, placing its
 * students in them, and the teacher grid that says who teaches what in each.
 */
class ClassroomController extends Controller
{
    public function store(CreateClassroomsRequest $request, SchoolClass $schoolClass, CreateClassrooms $createClassrooms): JsonResponse
    {
        $sections = $createClassrooms->handle($schoolClass, $request->integer('count'), $request->integer('capacity'));

        return response()->json([
            'data' => $sections->map(fn (Section $section): array => ['id' => $section->id, 'name' => $section->name, 'capacity' => $section->capacity])->all(),
        ], 201);
    }

    public function distribute(DistributeStudentsRequest $request, SchoolClass $schoolClass, DistributeStudents $distributeStudents): JsonResponse
    {
        return response()->json(['data' => $distributeStudents->handle($schoolClass, $request->validated('order'))]);
    }

    /**
     * Each classroom's class teacher and its subject teachers, for every
     * subject the class offers.
     */
    public function teacherGrid(SchoolClass $schoolClass): JsonResponse
    {
        return response()->json(['data' => $this->grid($schoolClass)]);
    }

    public function saveTeacherGrid(SaveTeacherGridRequest $request, SchoolClass $schoolClass, SaveTeacherGrid $saveTeacherGrid): JsonResponse
    {
        $saveTeacherGrid->handle($schoolClass, $request->validated('sections'));

        return response()->json(['data' => $this->grid($schoolClass)]);
    }

    /**
     * @return array{class: array{id: int, name: string, academic_year: array{id: int, name: string}}, subjects: list<array{id: int, code: string, name: string}>, sections: list<array{id: int, name: string, capacity: int|null, class_teacher_id: int|null, subject_teachers: list<array{subject_id: int, teacher_id: int}>}>}
     */
    private function grid(SchoolClass $schoolClass): array
    {
        $schoolClass->loadMissing('academicYear');
        $subjects = $schoolClass->subjects()->orderBy('name')->get(['subjects.id', 'subjects.code', 'subjects.name']);
        $sections = $schoolClass->sections()->with('subjectTeachers')->orderBy('name')->get();

        return [
            'class' => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'academic_year' => ['id' => $schoolClass->academicYear->id, 'name' => $schoolClass->academicYear->name],
            ],
            'subjects' => $subjects->map(fn ($subject): array => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name])->values()->all(),
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'class_teacher_id' => $section->class_teacher_id,
                'subject_teachers' => $section->subjectTeachers->map(fn ($assignment): array => ['subject_id' => $assignment->subject_id, 'teacher_id' => $assignment->teacher_id])->values()->all(),
            ])->values()->all(),
        ];
    }
}
