<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Attendance\Actions\AttendanceScores;
use App\Domain\Student\Models\Student;

/**
 * A student's scores side by side: the academic score (overall and per
 * subject) and the attendance score, out of the client's scale. Never
 * combined. Lifetime unless one academic year is asked for.
 */
class StudentScoreCard
{
    public function __construct(private ConsolidateSubjectScores $academic, private AttendanceScores $attendance) {}

    /**
     * @return array{scale: int, academic: array<string, mixed>, attendance: array<string, mixed>}
     */
    public function handle(Student $student, bool $publishedOnly = false, ?int $academicYearId = null): array
    {
        $academic = $this->academic->handle($student, $publishedOnly, $academicYearId);
        $measure = $this->attendance->combined($student->enrollments()->when($academicYearId, fn ($enrollments) => $enrollments->where('academic_year_id', $academicYearId))->pluck('id')->all());
        $scale = $academic['scale'];

        return [
            'scale' => $scale,
            'academic' => [
                'score' => $academic['overall']['score'] ?? null,
                'average_percentage' => $academic['overall']['average_percentage'] ?? null,
                'subjects' => $academic['subjects'],
            ],
            'attendance' => [
                'score' => $measure['percentage'] === null ? null : round($measure['percentage'] / 100 * $scale, 1),
                'percentage' => $measure['percentage'],
                'days_marked' => $measure['days_marked'],
                'days_attended' => $measure['days_attended'],
            ],
        ];
    }
}
