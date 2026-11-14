<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Actions\RecordExamMarks;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamMarkSheet;
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
     * The sheet: the section's students the user may see in the exam's
     * year, in roll-number order with any marks entered, the paper's
     * maximum marks and what the user may change.
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
            ->whereHas('student', fn (Builder $students) => $students->visibleTo($user, $exam->academic_year_id))
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

    /**
     * Each subject of the classroom's class for the exam: who teaches it,
     * whether its marks have been submitted and by whom, and whether the user
     * may enter them. Every class teacher sees every subject's status.
     */
    public function sheets(Request $request, Exam $exam, Section $section): JsonResponse
    {
        $section->loadMissing('schoolClass');

        if ($section->schoolClass->academic_year_id !== $exam->academic_year_id) {
            throw ValidationException::withMessages(['section_id' => "{$section->schoolClass->name} {$section->name} is not in {$exam->name}'s academic year."]);
        }

        $user = $request->user();
        $subjects = $section->schoolClass->subjects()->orderBy('name')->get(['subjects.id', 'subjects.code', 'subjects.name']);
        $teachers = $section->subjectTeachers()->with('teacher:id,name')->get()->keyBy('subject_id');
        $sheets = ExamMarkSheet::query()
            ->where('exam_id', $exam->id)
            ->where('section_id', $section->id)
            ->with('enteredBy:id,name')
            ->get()
            ->keyBy('subject_id');

        return response()->json([
            'data' => [
                'exam' => ['id' => $exam->id, 'name' => $exam->name, 'is_published' => $exam->isPublished(), 'published_at' => $exam->published_at?->toIso8601String()],
                'subjects' => $subjects->map(function (Subject $subject) use ($user, $section, $teachers, $sheets): array {
                    $sheet = $sheets->get($subject->id);
                    $teacher = $teachers->get($subject->id)?->teacher;

                    return [
                        'id' => $subject->id,
                        'code' => $subject->code,
                        'name' => $subject->name,
                        'teacher' => $teacher ? ['id' => $teacher->id, 'name' => $teacher->name] : null,
                        'submitted_at' => $sheet?->submitted_at?->toIso8601String(),
                        'entered_by' => $sheet?->enteredBy ? ['id' => $sheet->enteredBy->id, 'name' => $sheet->enteredBy->name] : null,
                        'can_enter' => $user->can('enterMarks', [Exam::class, $section, $subject]),
                    ];
                })->values(),
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
