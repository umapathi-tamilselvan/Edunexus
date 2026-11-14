<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrols a student in a class for the class's year without a section yet
 * (D45). Used for admissions that name no section, and for students moving up
 * to the class. Distribution places them in a section later.
 *
 * Rules: one enrolment per student per year, as for a section.
 */
class EnrollStudentToClass
{
    public function handle(Student $student, SchoolClass $schoolClass): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $schoolClass): StudentEnrollment {
            if (StudentEnrollment::query()->where('student_id', $student->id)->where('academic_year_id', $schoolClass->academic_year_id)->exists()) {
                throw ValidationException::withMessages([
                    'class_id' => 'This student is already enrolled in this academic year.',
                ]);
            }

            return StudentEnrollment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $schoolClass->academic_year_id,
                'class_id' => $schoolClass->id,
                'section_id' => null,
                'roll_no' => null,
                'status' => EnrollmentStatus::Active,
            ])->load(['academicYear', 'schoolClass']);
        });
    }
}
