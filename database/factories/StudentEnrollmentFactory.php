<?php

namespace Database\Factories;

use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEnrollment>
 */
class StudentEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state. The class and the academic year always
     * follow the section's class.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'section_id' => Section::factory(),
            'academic_year_id' => fn (array $attributes): int => Section::query()->with('schoolClass')->findOrFail($attributes['section_id'])->schoolClass->academic_year_id,
            'class_id' => fn (array $attributes): int => Section::query()->findOrFail($attributes['section_id'])->class_id,
            'status' => EnrollmentStatus::Active,
        ];
    }
}
