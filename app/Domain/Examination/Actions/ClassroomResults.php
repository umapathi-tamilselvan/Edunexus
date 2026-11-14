<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Attendance\Actions\AttendanceScores;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A classroom's results for its year: each student's academic score per
 * subject and overall, and their attendance score for the year, kept apart.
 * Both are out of the client's score scale.
 *
 * Set-based: one read of the students, one of their marks, one of the papers
 * and one grouped read of attendance, however many students there are.
 */
class ClassroomResults
{
    public function __construct(private OrganizationContext $organization, private AttendanceScores $attendance) {}

    /**
     * @return array{scale: int, subjects: list<array{id: int, code: string, name: string}>, students: list<array<string, mixed>>}
     */
    public function handle(Section $section, User $viewer): array
    {
        $section->loadMissing('schoolClass.subjects');
        $scale = (int) $this->organization->settingOrDefault('results.scale');
        $yearId = $section->schoolClass->academic_year_id;

        /** @var Collection<int, StudentEnrollment> $enrollments */
        $enrollments = $section->enrollments()
            ->whereHas('student', fn (Builder $students) => $students->visibleTo($viewer, $yearId))
            ->with('student:id,name,admission_no')
            ->orderBy('roll_no')
            ->get();

        $ids = $enrollments->pluck('id')->all();

        $marks = ExamMark::query()
            ->join('student_enrollments', 'student_enrollments.id', '=', 'exam_marks.enrollment_id')
            ->select('exam_marks.*', 'student_enrollments.class_id as enrollment_class_id')
            ->whereIn('exam_marks.enrollment_id', $ids)
            ->where('is_absent', false)
            ->whereNotNull('marks')
            ->with(['exam:id,name,max_marks,starts_on,academic_year_id', 'exam.academicYear:id,name,starts_on', 'subject:id,code,name'])
            ->get();

        $papers = ExamPaper::query()
            ->where('class_id', $section->class_id)
            ->whereIn('exam_id', $marks->pluck('exam_id')->unique())
            ->get()
            ->keyBy(fn (ExamPaper $paper): string => "{$paper->exam_id}-{$paper->class_id}-{$paper->subject_id}");

        $subjects = SubjectAverages::bySubject($marks, $papers, $scale, fn (ExamMark $mark): int => $mark->enrollment_id);
        $overall = $marks->groupBy('enrollment_id')->map(fn (Collection $owned): ?float => SubjectAverages::overall($owned, $papers));
        $attendance = $this->attendance->perEnrollment($ids);

        return [
            'scale' => $scale,
            'subjects' => $section->schoolClass->subjects
                ->sortBy('name')
                ->map(fn ($subject): array => ['id' => $subject->id, 'code' => $subject->code, 'name' => $subject->name])
                ->values()
                ->all(),
            'students' => $enrollments->map(function (StudentEnrollment $enrollment) use ($scale, $subjects, $overall, $attendance): array {
                $average = $overall->get($enrollment->id);
                $measure = $attendance->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->id,
                    'roll_no' => $enrollment->roll_no,
                    'student' => ['id' => $enrollment->student->id, 'name' => $enrollment->student->name, 'admission_no' => $enrollment->student->admission_no],
                    'academic' => [
                        'score' => $average === null ? null : round($average / 100 * $scale, 1),
                        'average_percentage' => $average,
                        'subjects' => $subjects->get($enrollment->id, collect())->map(fn (array $subject): array => [
                            'subject_id' => $subject['subject']['id'],
                            'score' => $subject['score'],
                            'average_percentage' => $subject['average_percentage'],
                        ])->values()->all(),
                    ],
                    'attendance' => [
                        'score' => $measure['percentage'] === null ? null : round($measure['percentage'] / 100 * $scale, 1),
                        'percentage' => $measure['percentage'],
                        'days_marked' => $measure['days_marked'],
                    ],
                ];
            })->values()->all(),
        ];
    }
}
