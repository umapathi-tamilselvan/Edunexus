<?php

namespace Database\Factories;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'name' => fake()->unique()->bothify('?#'),
            'capacity' => 40,
        ];
    }
}
