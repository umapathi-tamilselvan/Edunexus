<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Examination\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state: an exam out of 100.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => fake()->unique()->bothify('Unit Test ##'),
            'max_marks' => 100,
        ];
    }
}
