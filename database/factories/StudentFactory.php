<?php

namespace Database\Factories;

use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_no' => fake()->unique()->numerify('ADM#####'),
            'name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-4 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
        ];
    }
}
