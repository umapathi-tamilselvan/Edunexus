<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use Illuminate\Support\Collection;

/**
 * Averages of exam percentages, the way scores are measured (§3.6). Each mark
 * becomes a percentage of its paper's maximum (the class's paper if it has
 * one, else the exam's maximum), every exam counts equally, and absent or
 * blank marks are left out rather than counted as zero.
 *
 * The marks passed in must have exam (with academicYear), subject and
 * enrollment.section loaded.
 */
final class SubjectAverages
{
    /**
     * The percentage one mark is of its paper's maximum.
     *
     * @param  Collection<string, ExamPaper>  $papers  keyed "exam-class-subject"
     */
    public static function percentage(ExamMark $mark, Collection $papers): float
    {
        $classId = $mark->enrollment->section->class_id;
        $maxMarks = $papers->get("{$mark->exam_id}-{$classId}-{$mark->subject_id}")?->max_marks ?? $mark->exam->max_marks;

        return (float) $mark->marks / $maxMarks * 100;
    }

    /**
     * The average percentage over every mark given, every exam counting
     * equally, or null when no mark has been given.
     *
     * @param  Collection<int, ExamMark>  $marks
     * @param  Collection<string, ExamPaper>  $papers
     */
    public static function overall(Collection $marks, Collection $papers): ?float
    {
        $percentages = $marks
            ->filter(fn (ExamMark $mark): bool => ! $mark->is_absent && $mark->marks !== null)
            ->map(fn (ExamMark $mark): float => self::percentage($mark, $papers));

        return $percentages->isEmpty() ? null : round($percentages->avg(), 1);
    }

    /**
     * Each owner's subject averages, subjects in name order. The owner is
     * whatever $ownerOf returns: an enrollment, or one key for the student.
     *
     * @param  Collection<int, ExamMark>  $marks
     * @param  Collection<string, ExamPaper>  $papers
     * @param  callable(ExamMark): int|string  $ownerOf
     * @return Collection<int|string, Collection<int, array{subject: array{id: int, code: string, name: string}, score: float, average_percentage: float, exams: list<array{exam_id: int, exam: string, academic_year: string, marks: float, max_marks: int, percentage: float}>}>>
     */
    public static function bySubject(Collection $marks, Collection $papers, int $scale, callable $ownerOf): Collection
    {
        return $marks
            ->filter(fn (ExamMark $mark): bool => ! $mark->is_absent && $mark->marks !== null)
            ->groupBy($ownerOf)
            ->map(fn (Collection $owned): Collection => $owned
                ->groupBy('subject_id')
                ->map(fn (Collection $subjectMarks): array => self::subject($subjectMarks, $papers, $scale))
                ->sortBy(fn (array $subject): string => $subject['subject']['name'])
                ->values());
    }

    /**
     * @param  Collection<int, ExamMark>  $subjectMarks
     * @param  Collection<string, ExamPaper>  $papers
     * @return array{subject: array{id: int, code: string, name: string}, score: float, average_percentage: float, exams: list<array{exam_id: int, exam: string, academic_year: string, marks: float, max_marks: int, percentage: float}>}
     */
    private static function subject(Collection $subjectMarks, Collection $papers, int $scale): array
    {
        $exams = $subjectMarks
            ->sortBy(fn (ExamMark $mark): string => ($mark->exam->starts_on ?? $mark->exam->academicYear->starts_on)->toDateString().$mark->exam->name)
            ->map(fn (ExamMark $mark): array => [
                'exam_id' => $mark->exam_id,
                'exam' => $mark->exam->name,
                'academic_year' => $mark->exam->academicYear->name,
                'marks' => $mark->marks,
                'max_marks' => $papers->get("{$mark->exam_id}-{$mark->enrollment->section->class_id}-{$mark->subject_id}")?->max_marks ?? $mark->exam->max_marks,
                'percentage' => round(self::percentage($mark, $papers), 1),
            ])
            ->values();

        $average = $exams->avg('percentage');
        $subject = $subjectMarks->first()->subject;

        return [
            'subject' => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name],
            'score' => round($average / 100 * $scale, 1),
            'average_percentage' => round($average, 1),
            'exams' => $exams->all(),
        ];
    }
}
