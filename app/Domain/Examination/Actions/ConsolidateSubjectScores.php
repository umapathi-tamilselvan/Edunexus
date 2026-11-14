<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Student\Models\Student;
use App\Support\OrganizationContext;

/**
 * A student's academic score in each subject, out of the client's score
 * scale (results.scale, default 10), consolidated from every exam they have
 * taken in any year. Lifetime measure: the attendance score is kept apart
 * (AttendanceScores), and the two are never combined here.
 *
 * Every exam counts equally: each mark becomes a percentage of its paper's
 * maximum, the percentages are averaged, and the average is scaled to the
 * client's scale. Exams the student was absent for are left out rather than
 * counted as zero.
 */
class ConsolidateSubjectScores
{
    public function __construct(private OrganizationContext $organization) {}

    /**
     * @return array{scale: int, overall: array{score: float, average_percentage: float}|null, subjects: list<array{subject: array{id: int, code: string, name: string}, score: float, average_percentage: float, exams: list<array{exam_id: int, exam: string, academic_year: string, marks: float, max_marks: int, percentage: float}>}>}
     */
    public function handle(Student $student): array
    {
        $scale = (int) $this->organization->settingOrDefault('results.scale');

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

        $subjects = SubjectAverages::bySubject($marks, $papers, $scale, fn (): string => 'lifetime')->get('lifetime', collect());
        $overall = SubjectAverages::overall($marks, $papers);

        return [
            'scale' => $scale,
            'overall' => $overall === null ? null : ['score' => round($overall / 100 * $scale, 1), 'average_percentage' => $overall],
            'subjects' => $subjects->all(),
        ];
    }
}
