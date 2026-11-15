<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\BulkAudit;
use Illuminate\Database\Eloquent\Builder;

/**
 * When a year closes, its final class (the highest sort order) is complete
 * (D49): its students who are not enrolled in the year that starts become
 * Left. Bulk change, audited with BulkAudit.
 */
class CompleteFinalClass
{
    /**
     * The year that closes when the given year becomes current: the year that
     * starts before it, the latest one. Null for the first year.
     */
    public function closingYearFor(AcademicYear $nextYear): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('starts_on', '<', $nextYear->starts_on)
            ->orderByDesc('starts_on')
            ->first();
    }

    /**
     * What making the next year current would do: the final class of the year
     * that closes, and how many of its students would become left. Read only.
     *
     * @return array{closing_year: array{id: int, name: string}|null, final_class: array{id: int, name: string}|null, students_to_leave: int}
     */
    public function preview(AcademicYear $closedYear, AcademicYear $nextYear): array
    {
        $finalClass = $this->finalClass($closedYear);

        return [
            'closing_year' => ['id' => $closedYear->id, 'name' => $closedYear->name],
            'final_class' => $finalClass === null ? null : ['id' => $finalClass->id, 'name' => $finalClass->name],
            'students_to_leave' => $finalClass === null ? 0 : $this->pending($finalClass, $closedYear, $nextYear)->count(),
        ];
    }

    public function handle(AcademicYear $closedYear, AcademicYear $nextYear): int
    {
        $finalClass = $this->finalClass($closedYear);

        if ($finalClass === null) {
            return 0;
        }

        $enrollments = $this->pending($finalClass, $closedYear, $nextYear)->get(['id', 'status']);

        if ($enrollments->isEmpty()) {
            return 0;
        }

        $changes = $enrollments->map(fn (StudentEnrollment $enrollment): array => [
            'id' => $enrollment->id,
            'event' => 'updated',
            'old' => ['status' => $enrollment->status->value],
            'new' => ['status' => EnrollmentStatus::Left->value],
        ])->all();

        StudentEnrollment::query()->whereKey($enrollments->modelKeys())->update(['status' => EnrollmentStatus::Left]);

        // Bulk writes fire no model events; the change is audited here.
        BulkAudit::record(StudentEnrollment::class, $changes);

        return count($changes);
    }

    private function finalClass(AcademicYear $closedYear): ?SchoolClass
    {
        return SchoolClass::query()
            ->where('academic_year_id', $closedYear->id)
            ->orderByDesc('sort_order')
            ->first();
    }

    /**
     * The final class's students who are not left yet and not enrolled in the next year.
     */
    private function pending(SchoolClass $finalClass, AcademicYear $closedYear, AcademicYear $nextYear): Builder
    {
        return StudentEnrollment::query()
            ->where('academic_year_id', $closedYear->id)
            ->where('class_id', $finalClass->id)
            ->where('status', '!=', EnrollmentStatus::Left)
            ->whereNotIn('student_id', StudentEnrollment::query()->where('academic_year_id', $nextYear->id)->select('student_id'));
    }
}
