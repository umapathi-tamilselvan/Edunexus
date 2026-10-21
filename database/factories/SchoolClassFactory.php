<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->unique()->numberBetween(1, 99);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Grade '.$grade,
            'sort_order' => $grade,
        ];
    }
}
