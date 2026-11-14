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
 * Pass $publishedOnly to count only exams whose marks are published (what a
 * guardian may see), and $academicYearId to count one year only.
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
    public function handle(Student $student, bool $publishedOnly = false, ?int $academicYearId = null): array
    {
        $scale = (int) $this->organization->settingOrDefault('results.scale');

        $marks = ExamMark::query()
            ->join('student_enrollments', 'student_enrollments.id', '=', 'exam_marks.enrollment_id')
            ->select('exam_marks.*', 'student_enrollments.class_id as enrollment_class_id')
            ->whereIn('exam_marks.enrollment_id', $student->enrollments()->when($academicYearId, fn ($enrollments) => $enrollments->where('academic_year_id', $academicYearId))->select('id'))
            ->where('is_absent', false)
            ->whereNotNull('marks')
            ->when($publishedOnly, fn ($query) => $query->whereHas('exam', fn ($exams) => $exams->whereNotNull('published_at')))
            ->with(['exam:id,name,max_marks,starts_on,academic_year_id', 'exam.academicYear:id,name,starts_on', 'subject:id,code,name'])
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
