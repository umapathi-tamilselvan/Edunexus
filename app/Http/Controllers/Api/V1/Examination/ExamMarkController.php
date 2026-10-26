<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Actions\RecordExamMarks;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Student\Models\StudentEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Examination\RecordExamMarksRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Marks sheets: one section's marks in one subject of an exam.
 */
class ExamMarkController extends Controller
{
    /**
     * The sheet: the section's students the user may see, in roll-number
     * order with any marks entered, the paper's maximum marks and what the
     * user may change.
     */
    public function index(Request $request, Exam $exam): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
        ]);

        $section = Section::query()->with('schoolClass')->findOrFail($validated['section_id']);
        $subject = Subject::query()->findOrFail($validated['subject_id']);
        $user = $request->user();

        if ($section->schoolClass->academic_year_id !== $exam->academic_year_id) {
            throw ValidationException::withMessages(['section_id' => "{$section->schoolClass->name} {$section->name} is not in {$exam->name}'s academic year."]);
        }

        $enrollments = $section->enrollments()
            ->whereHas('student', fn (Builder $students) => $students->visibleTo($user))
            ->with('student:id,name,admission_no')
            ->orderBy('roll_no')
            ->get();

        $marks = ExamMark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->keyBy('enrollment_id');

        return response()->json([
            'data' => [
                'exam' => ['id' => $exam->id, 'name' => $exam->name],
                'section' => ['id' => $section->id, 'name' => $section->name, 'class' => ['id' => $section->schoolClass->id, 'name' => $section->schoolClass->name]],
                'subject' => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name],
                'max_marks' => $exam->maxMarksFor($section->class_id, $subject->id),
                'can_enter_marks' => $user->can('enterMarks', [Exam::class, $section, $subject]),
                'can_set_max_marks' => $user->can(Permission::ExamManage->value),
                'students' => $enrollments->map(fn (StudentEnrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'roll_no' => $enrollment->roll_no,
                    'status' => $enrollment->status->value,
                    'student' => ['id' => $enrollment->student->id, 'name' => $enrollment->student->name, 'admission_no' => $enrollment->student->admission_no],
                    'marks' => $marks->get($enrollment->id)?->marks,
                    'is_absent' => $marks->get($enrollment->id)->is_absent ?? false,
                ]),
            ],
        ]);
    }

    public function update(RecordExamMarksRequest $request, Exam $exam, RecordExamMarks $recordExamMarks): Response
    {
        $section = Section::query()->findOrFail($request->validated('section_id'));
        $subject = Subject::query()->findOrFail($request->validated('subject_id'));

        Gate::authorize('enterMarks', [Exam::class, $section, $subject]);

        $recordExamMarks->handle($exam, $section, $subject, $request->validated('entries'), $request->user());

        return response()->noContent();
    }
}
