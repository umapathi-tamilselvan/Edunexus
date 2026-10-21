<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 12);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Period '.$number,
            'starts_at' => sprintf('%02d:00', 7 + $number),
            'ends_at' => sprintf('%02d:45', 7 + $number),
            'is_break' => false,
            'sort_order' => $number,
        ];
    }

    public function break(): static
    {
        return $this->state(fn (): array => ['name' => 'Lunch', 'is_break' => true]);
    }
}
