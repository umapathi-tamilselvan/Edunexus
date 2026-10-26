<?php

namespace Database\Factories;

use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamMark>
 */
class ExamMarkFactory extends Factory
{
    /**
     * Define the model's default state. The exam is in the enrollment's year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => StudentEnrollment::factory(),
            'exam_id' => fn (array $attributes): int => Exam::factory()->create([
                'academic_year_id' => StudentEnrollment::query()->findOrFail($attributes['enrollment_id'])->academic_year_id,
            ])->id,
            'subject_id' => Subject::factory(),
            'marks' => fake()->numberBetween(0, 100),
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (): array => ['marks' => null, 'is_absent' => true]);
    }
}
