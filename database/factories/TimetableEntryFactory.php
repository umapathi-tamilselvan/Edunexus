<?php

namespace Database\Factories;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'weekday' => Weekday::Monday,
            'period_id' => fn (array $attributes): int => Period::factory()->create([
                'academic_year_id' => Section::query()->with('schoolClass')->findOrFail($attributes['section_id'])->schoolClass->academic_year_id,
            ])->id,
            'subject_id' => Subject::factory(),
        ];
    }
}
