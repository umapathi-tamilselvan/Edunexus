<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a student with their guardians and, optionally, an enrollment,
 * all or nothing.
 */
class CreateStudent
{
    public function __construct(
        private LinkGuardian $linkGuardian,
        private EnrollStudent $enrollStudent,
        private EnrollStudentToClass $enrollStudentToClass,
    ) {}

    /**
     * @param  list<array{name: string, phone: string, email?: string|null, relation: string, is_primary?: bool}>  $guardians
     * @param  array{section_id?: int, class_id?: int, roll_no?: int|null}|null  $enrollment
     */
    public function handle(string $admissionNo, string $name, Carbon $dateOfBirth, Gender $gender, array $guardians, ?array $enrollment = null): Student
    {
        return DB::transaction(function () use ($admissionNo, $name, $dateOfBirth, $gender, $guardians, $enrollment): Student {
            $student = Student::query()->create([
                'admission_no' => $admissionNo,
                'name' => $name,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
            ]);

            foreach ($guardians as $guardian) {
                $this->linkGuardian->handle($student, $guardian, GuardianRelation::from($guardian['relation']), (bool) ($guardian['is_primary'] ?? false));
            }

            if ($enrollment !== null) {
                if (isset($enrollment['section_id'])) {
                    $this->enrollStudent->handle($student, Section::query()->findOrFail($enrollment['section_id']), $enrollment['roll_no'] ?? null);
                } else {
                    $this->enrollStudentToClass->handle($student, SchoolClass::query()->findOrFail($enrollment['class_id']));
                }
            }

            return $student->load(['guardians', 'enrollments.academicYear', 'enrollments.schoolClass', 'enrollments.section.schoolClass']);
        });
    }
}
