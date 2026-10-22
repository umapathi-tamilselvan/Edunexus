<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves one section's marks in one subject of an exam: the marks sheet a
 * subject teacher fills in.
 *
 * Rules: the section is in the exam's academic year, the subject is offered
 * to its class, every student is enrolled in the section, and no marks
 * exceed the paper's maximum. An absent student has no marks; a student
 * with neither marks nor absence has their entry cleared.
 */
class RecordExamMarks
{
    /**
     * @param  list<array{enrollment_id: int, marks: float|int|string|null, is_absent?: bool}>  $entries
     */
    public function handle(Exam $exam, Section $section, Subject $subject, array $entries, User $recordedBy): void
    {
        $section->loadMissing('schoolClass');

        if ($section->schoolClass->academic_year_id !== $exam->academic_year_id) {
            throw ValidationException::withMessages(['section_id' => "{$section->schoolClass->name} {$section->name} is not in {$exam->name}'s academic year."]);
        }

        if (! $section->schoolClass->subjects()->whereKey($subject->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => "{$subject->name} is not offered to {$section->schoolClass->name}."]);
        }

        $enrolled = $section->enrollments()->pluck('id')->all();
        $maxMarks = $exam->maxMarksFor($section->class_id, $subject->id);
        $errors = [];

        foreach ($entries as $index => $entry) {
            if (! in_array((int) $entry['enrollment_id'], $enrolled, true)) {
                $errors["entries.{$index}.enrollment_id"] = "This student is not in {$section->schoolClass->name} {$section->name}.";
            } elseif (! ($entry['is_absent'] ?? false) && $entry['marks'] !== null && (float) $entry['marks'] > $maxMarks) {
                $errors["entries.{$index}.marks"] = "Marks cannot be more than {$maxMarks}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($exam, $subject, $entries, $recordedBy): void {
            foreach ($entries as $entry) {
                $key = ['exam_id' => $exam->id, 'enrollment_id' => (int) $entry['enrollment_id'], 'subject_id' => $subject->id];
                $isAbsent = (bool) ($entry['is_absent'] ?? false);

                if (! $isAbsent && $entry['marks'] === null) {
                    // Deleted one by one, not in bulk, so the audit trail keeps the old marks.
                    ExamMark::query()->where($key)->first()?->delete();

                    continue;
                }

                $mark = ExamMark::query()->firstOrNew($key);
                $mark->fill(['marks' => $isAbsent ? null : (float) $entry['marks'], 'is_absent' => $isAbsent]);

                if ($mark->isDirty()) {
                    $mark->recorded_by = $recordedBy->id;
                    $mark->save();
                }
            }
        });
    }
}
