<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\BulkAudit;

/**
 * When a year closes, its final class (the highest sort order) is complete
 * (D49): its students who are not enrolled in the year that starts become
 * Left. Bulk change, audited with BulkAudit.
 */
class CompleteFinalClass
{
    public function handle(AcademicYear $closedYear, AcademicYear $nextYear): int
    {
        $finalClass = SchoolClass::query()
            ->where('academic_year_id', $closedYear->id)
            ->orderByDesc('sort_order')
            ->first();

        if ($finalClass === null) {
            return 0;
        }

        $enrollments = StudentEnrollment::query()
            ->where('academic_year_id', $closedYear->id)
            ->where('class_id', $finalClass->id)
            ->where('status', '!=', EnrollmentStatus::Left)
            ->whereNotIn('student_id', StudentEnrollment::query()->where('academic_year_id', $nextYear->id)->select('student_id'))
            ->get(['id', 'status']);

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
}
