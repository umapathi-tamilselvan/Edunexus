<?php

namespace Database\Factories;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSubjectTeacher>
 */
class SectionSubjectTeacherFactory extends Factory
{
    /**
     * Define the model's default state. The teacher teaches the subject.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => fn (array $attributes): int => tap(User::factory()->create(), fn (User $teacher) => $teacher->subjects()->attach($attributes['subject_id']))->id,
        ];
    }
}
