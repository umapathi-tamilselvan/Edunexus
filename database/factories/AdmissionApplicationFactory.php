<?php

namespace Database\Factories;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Enums\GuardianRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionApplication>
 */
class AdmissionApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_no' => fake()->unique()->numerify('TEST/####'),
            'class_id' => SchoolClass::factory(),
            'academic_year_id' => fn (array $attributes): int => SchoolClass::query()->findOrFail($attributes['class_id'])->academic_year_id,
            'status' => ApplicationStatus::Enquiry,
            'student_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-12 years', '-4 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->unique()->numerify('9#########'),
            'guardian_email' => fake()->safeEmail(),
            'guardian_relation' => GuardianRelation::Mother,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => ApplicationStatus::Approved]);
    }
}
