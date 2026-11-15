<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamMarkSheet;
use App\Domain\Identity\Models\User;
use App\Support\BulkAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves one section's marks in one subject of an exam: the marks sheet a
 * subject teacher fills in, in bulk.
 *
 * Rules: the exam is not published, the section is in the exam's academic
 * year, the subject is offered to its class, every student is enrolled in
 * the section, and no marks exceed the paper's maximum. An absent student
 * has no marks; a student with neither marks nor absence has their entry
 * cleared. A save that carries any marks or absences submits the sheet; its
 * first submission sets the time, later saves change only who entered it.
 *
 * Queries: one read of the entries, one upsert of the marks, one read of
 * the new marks' ids (so they can be audited), at most one delete, and one
 * upsert of the sheet. Every change is audited with BulkAudit.
 */
class RecordExamMarks
{
    /**
     * @param  list<array{enrollment_id: int, marks: float|int|string|null, is_absent?: bool}>  $entries
     */
    public function handle(Exam $exam, Section $section, Subject $subject, array $entries, User $recordedBy): void
    {
        if ($exam->isPublished()) {
            throw ValidationException::withMessages(['exam' => 'These marks are published and locked. Someone with the unlock permission must reopen the exam first.']);
        }

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

        $now = now();

        DB::transaction(function () use ($exam, $section, $subject, $entries, $recordedBy, $now): void {
            $existing = ExamMark::query()
                ->where('exam_id', $exam->id)
                ->where('subject_id', $subject->id)
                ->whereIn('enrollment_id', array_map(fn (array $entry): int => (int) $entry['enrollment_id'], $entries))
                ->lockForUpdate()
                ->get()
                ->keyBy('enrollment_id');

            $rows = [];
            $created = [];
            $deleted = [];
            $changes = [];
            $submitted = false;

            foreach ($entries as $entry) {
                $enrollmentId = (int) $entry['enrollment_id'];
                $isAbsent = (bool) ($entry['is_absent'] ?? false);
                $mark = $existing->get($enrollmentId);

                if (! $isAbsent && $entry['marks'] === null) {
                    if ($mark !== null) {
                        $deleted[] = $mark->id;
                        $changes[] = ['id' => $mark->id, 'event' => 'deleted', 'old' => self::values($mark->getRawOriginal('marks'), $mark->is_absent), 'new' => []];
                    }

                    continue;
                }

                $submitted = true;
                $marks = $isAbsent ? null : (float) $entry['marks'];

                if ($mark !== null && $mark->marks === $marks && $mark->is_absent === $isAbsent) {
                    continue;
                }

                if ($mark !== null) {
                    $changes[] = ['id' => $mark->id, 'event' => 'updated', 'old' => self::values($mark->getRawOriginal('marks'), $mark->is_absent), 'new' => self::values($marks, $isAbsent)];
                } else {
                    // Its id is filled in after the upsert, in the same place, so the audit keeps the entry order.
                    $created[$enrollmentId] = count($changes);
                    $changes[] = ['id' => null, 'event' => 'created', 'old' => [], 'new' => self::values($marks, $isAbsent)];
                }

                $rows[] = [
                    'exam_id' => $exam->id,
                    'enrollment_id' => $enrollmentId,
                    'subject_id' => $subject->id,
                    'marks' => $marks,
                    'is_absent' => $isAbsent,
                    'recorded_by' => $recordedBy->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                ExamMark::query()->upsert($rows, ['exam_id', 'enrollment_id', 'subject_id'], ['marks', 'is_absent', 'recorded_by', 'updated_at']);
            }

            if ($created !== []) {
                ExamMark::query()
                    ->where('exam_id', $exam->id)
                    ->where('subject_id', $subject->id)
                    ->whereIn('enrollment_id', array_keys($created))
                    ->get(['id', 'enrollment_id'])
                    ->each(function (ExamMark $mark) use (&$changes, $created): void {
                        $changes[$created[$mark->enrollment_id]]['id'] = $mark->id;
                    });
            }

            if ($deleted !== []) {
                ExamMark::query()->whereKey($deleted)->delete();
            }

            if ($submitted) {
                ExamMarkSheet::query()->upsert(
                    [[
                        'exam_id' => $exam->id,
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                        'entered_by' => $recordedBy->id,
                        'submitted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['exam_id', 'section_id', 'subject_id'],
                    ['entered_by', 'updated_at'],
                );
            }

            // Bulk writes fire no model events; every change is audited here.
            BulkAudit::record(ExamMark::class, $changes);
        });
    }

    /**
     * @return array{marks: float|null, is_absent: bool}
     */
    private static function values(float|string|null $marks, bool $isAbsent): array
    {
        return ['marks' => $marks, 'is_absent' => $isAbsent];
    }
}
