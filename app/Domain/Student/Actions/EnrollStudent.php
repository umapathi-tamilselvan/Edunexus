<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls a student into a section for that section's academic year.
 *
 * Rules: one enrollment per student per year, the section's capacity is
 * respected, and roll numbers are unique within a section (the next free
 * one is assigned when none is given).
 */
class EnrollStudent
{
    public function handle(Student $student, Section $section, ?int $rollNo = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $section, $rollNo): StudentEnrollment {
            $section = SectionSeats::lock($section);
            $academicYearId = $section->schoolClass->academic_year_id;

            if (StudentEnrollment::query()->where('student_id', $student->id)->where('academic_year_id', $academicYearId)->exists()) {
                throw ValidationException::withMessages([
                    'section_id' => 'This student is already enrolled in this academic year.',
                ]);
            }

            SectionSeats::ensureAvailable($section);

            return StudentEnrollment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
                'class_id' => $section->class_id,
                'section_id' => $section->id,
                'roll_no' => SectionSeats::rollNumber($section, $rollNo),
                'status' => EnrollmentStatus::Active,
            ])->load(['academicYear', 'schoolClass', 'section.schoolClass']);
        });
    }
}
