<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\AssignSubjectTeacher;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\AssignSubjectTeacherRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who teaches each subject to a section.
 */
class SectionSubjectTeacherController extends Controller
{
    /**
     * Sets or clears the teacher of one subject in a section.
     */
    public function update(AssignSubjectTeacherRequest $request, Section $section, Subject $subject, AssignSubjectTeacher $assignSubjectTeacher): JsonResponse
    {
        $teacherId = $request->validated('teacher_id');
        $teacher = $teacherId === null ? null : User::query()->findOrFail($teacherId);

        $assignSubjectTeacher->handle($section, $subject, $teacher);

        return response()->json([
            'data' => [
                'subject_id' => $subject->id,
                'teacher' => $teacher ? ['id' => $teacher->id, 'name' => $teacher->name] : null,
            ],
        ]);
    }

    /**
     * The sections and subjects the signed-in user teaches in the current year.
     */
    public function mine(Request $request): JsonResponse
    {
        $assignments = $request->user()->subjectTeaching()
            ->whereHas('section.schoolClass.academicYear', fn ($year) => $year->where('is_current', true))
            ->with(['subject', 'section.schoolClass'])
            ->get()
            ->sortBy(fn (SectionSubjectTeacher $assignment): string => sprintf('%05d%s%s', $assignment->section->schoolClass->sort_order, $assignment->section->schoolClass->name, $assignment->section->name))
            ->values();

        return response()->json([
            'data' => $assignments->map(fn (SectionSubjectTeacher $assignment): array => [
                'section' => ['id' => $assignment->section->id, 'name' => $assignment->section->name, 'class' => ['id' => $assignment->section->schoolClass->id, 'name' => $assignment->section->schoolClass->name]],
                'subject' => ['id' => $assignment->subject->id, 'code' => $assignment->subject->code, 'name' => $assignment->subject->name],
            ]),
        ]);
    }
}
