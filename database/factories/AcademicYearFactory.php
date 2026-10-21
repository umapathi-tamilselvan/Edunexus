<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state: a June-to-April year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $startYear.'-'.substr((string) ($startYear + 1), -2),
            'starts_on' => Carbon::create($startYear, 6, 1),
            'ends_on' => Carbon::create($startYear + 1, 4, 30),
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => ['is_current' => true]);
    }
}
