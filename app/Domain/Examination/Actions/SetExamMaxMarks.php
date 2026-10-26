<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use Illuminate\Validation\ValidationException;

/**
 * Sets the maximum marks of one class's subject in an exam, e.g. Class 1
 * Computer Science out of 50 while the exam is out of 100. Setting it back
 * to the exam's maximum removes the difference.
 *
 * Rules: the class is in the exam's academic year, the subject is offered
 * to it, and no student already has more marks than the new maximum.
 */
class SetExamMaxMarks
{
    public function handle(Exam $exam, SchoolClass $schoolClass, Subject $subject, int $maxMarks): int
    {
        if ($schoolClass->academic_year_id !== $exam->academic_year_id) {
            throw ValidationException::withMessages(['class_id' => "{$schoolClass->name} is not in {$exam->name}'s academic year."]);
        }

        if (! $schoolClass->subjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => "{$subject->name} is not offered to {$schoolClass->name}."]);
        }

        $highest = ExamMark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereHas('enrollment.section', fn ($section) => $section->where('class_id', $schoolClass->id))
            ->max('marks');

        if ($highest !== null && (float) $highest > $maxMarks) {
            throw ValidationException::withMessages(['max_marks' => sprintf('A student already has %s marks, so the maximum cannot be lower.', (float) $highest)]);
        }

        $key = ['exam_id' => $exam->id, 'class_id' => $schoolClass->id, 'subject_id' => $subject->id];

        if ($maxMarks === $exam->max_marks) {
            ExamPaper::query()->where($key)->delete();
        } else {
            ExamPaper::query()->updateOrCreate($key, ['max_marks' => $maxMarks]);
        }

        return $maxMarks;
    }
}
