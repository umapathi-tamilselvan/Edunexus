<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Collection;

/**
 * A student's score out of 5 in each subject, consolidated from every exam
 * they have taken in any year.
 *
 * Every exam counts equally: each exam's marks become a percentage of that
 * paper's maximum, the percentages are averaged, and the average is scaled
 * to 5. Exams the student was absent for are left out rather than counted
 * as zero.
 */
class ConsolidateSubjectScores
{
    /**
     * @return list<array{
     *     subject: array{id: int, code: string, name: string},
     *     score: float,
     *     average_percentage: float,
     *     exams: list<array{exam_id: int, exam: string, academic_year: string, marks: float, max_marks: int, percentage: float}>
     * }>
     */
    public function handle(Student $student): array
    {
        $marks = ExamMark::query()
            ->whereIn('enrollment_id', $student->enrollments()->select('id'))
            ->where('is_absent', false)
            ->whereNotNull('marks')
            ->with(['exam.academicYear', 'subject', 'enrollment.section'])
            ->get();

        $papers = ExamPaper::query()
            ->whereIn('exam_id', $marks->pluck('exam_id')->unique())
            ->get()
            ->keyBy(fn (ExamPaper $paper): string => "{$paper->exam_id}-{$paper->class_id}-{$paper->subject_id}");

        return $marks
            ->groupBy('subject_id')
            ->map(function (Collection $subjectMarks) use ($papers): array {
                $exams = $subjectMarks
                    ->sortBy(fn (ExamMark $mark): string => ($mark->exam->starts_on ?? $mark->exam->academicYear->starts_on)->toDateString().$mark->exam->name)
                    ->map(function (ExamMark $mark) use ($papers): array {
                        $maxMarks = $papers->get("{$mark->exam_id}-{$mark->enrollment->section->class_id}-{$mark->subject_id}")?->max_marks ?? $mark->exam->max_marks;

                        return [
                            'exam_id' => $mark->exam_id,
                            'exam' => $mark->exam->name,
                            'academic_year' => $mark->exam->academicYear->name,
                            'marks' => $mark->marks,
                            'max_marks' => $maxMarks,
                            'percentage' => $mark->marks / $maxMarks * 100,
                        ];
                    })
                    ->values();

                $average = $exams->avg('percentage');
                $subject = $subjectMarks->first()->subject;

                return [
                    'subject' => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name],
                    'score' => round($average / 100 * 5, 1),
                    'average_percentage' => round($average, 1),
                    'exams' => $exams->map(fn (array $exam): array => [...$exam, 'percentage' => round($exam['percentage'], 1)])->all(),
                ];
            })
            ->sortBy('subject.name')
            ->values()
            ->all();
    }
}
