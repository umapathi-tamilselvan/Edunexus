<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Renames, reschedules or changes the maximum marks of an exam. The
 * maximum cannot drop below marks already entered in papers that use it.
 */
class UpdateExam
{
    /**
     * @param  array{name: string, max_marks: int, starts_on?: string|null, ends_on?: string|null}  $attributes
     */
    public function handle(Exam $exam, array $attributes): Exam
    {
        if ($attributes['max_marks'] !== $exam->max_marks && $exam->isPublished()) {
            throw ValidationException::withMessages(['max_marks' => 'The exam is published, so its maximum marks are locked. Reopen the exam first.']);
        }

        if ($attributes['max_marks'] < $exam->max_marks) {
            $highest = ExamMark::query()
                ->join('student_enrollments', 'student_enrollments.id', '=', 'exam_marks.enrollment_id')
                ->join('sections', 'sections.id', '=', 'student_enrollments.section_id')
                ->where('exam_marks.exam_id', $exam->id)
                ->whereNotExists(fn (Builder $papers) => $papers->selectRaw('1')->from('exam_papers')
                    ->whereColumn('exam_papers.exam_id', 'exam_marks.exam_id')
                    ->whereColumn('exam_papers.class_id', 'sections.class_id')
                    ->whereColumn('exam_papers.subject_id', 'exam_marks.subject_id'))
                ->max('exam_marks.marks');

            if ($highest !== null && (float) $highest > $attributes['max_marks']) {
                throw ValidationException::withMessages(['max_marks' => sprintf('A student already has %s marks, so the maximum cannot be lower.', (float) $highest)]);
            }
        }

        $exam->update($attributes);

        return $exam;
    }
}
