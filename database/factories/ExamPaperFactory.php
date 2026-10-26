<?php

namespace Database\Factories;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamPaper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPaper>
 */
class ExamPaperFactory extends Factory
{
    /**
     * Define the model's default state. The class is in the exam's year.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'class_id' => fn (array $attributes): int => SchoolClass::factory()->create([
                'academic_year_id' => Exam::query()->findOrFail($attributes['exam_id'])->academic_year_id,
            ])->id,
            'subject_id' => Subject::factory(),
            'max_marks' => 50,
        ];
    }
}
