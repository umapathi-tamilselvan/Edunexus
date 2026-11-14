<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the teacher grid of a class: each classroom's class teacher and the
 * teacher of each of the class's subjects in that classroom.
 *
 * Every cell is checked before anything is written, so one bad cell changes
 * nothing. Errors are keyed by row and column (sections.{row}.subjects.{column}.teacher_id)
 * so the grid can show them in place. Subject teachers are written through
 * AssignSubjectTeacher, which also moves the timetable slots.
 */
class SaveTeacherGrid
{
    public function __construct(private AssignSubjectTeacher $assignSubjectTeacher) {}

    /**
     * @param  list<array{section_id: int, class_teacher_id: int|null, subjects: list<array{subject_id: int, teacher_id: int|null}>}>  $rows
     */
    public function handle(SchoolClass $schoolClass, array $rows): void
    {
        $sections = $schoolClass->sections()->get()->keyBy('id');
        $subjects = $schoolClass->subjects()->get()->keyBy('id');
        $teachers = User::query()->with('subjects:id')->whereIn('id', $this->teacherIds($rows))->get()->keyBy('id');

        $this->ensureValid($rows, $sections->all(), $subjects->all(), $teachers->all());

        DB::transaction(function () use ($schoolClass, $rows, $sections, $subjects, $teachers): void {
            foreach ($rows as $row) {
                $section = $sections[$row['section_id']]->setRelation('schoolClass', $schoolClass);

                if ($row['class_teacher_id'] !== $section->class_teacher_id) {
                    $section->update(['class_teacher_id' => $row['class_teacher_id']]);
                }

                foreach ($row['subjects'] as $cell) {
                    $current = SectionSubjectTeacher::query()
                        ->where('section_id', $section->id)
                        ->where('subject_id', $cell['subject_id'])
                        ->value('teacher_id');

                    if ($current === $cell['teacher_id']) {
                        continue;
                    }

                    $this->assignSubjectTeacher->handle(
                        $section,
                        $subjects[$cell['subject_id']],
                        $cell['teacher_id'] === null ? null : $teachers[$cell['teacher_id']],
                    );
                }
            }
        });
    }

    /**
     * @param  list<array{section_id: int, class_teacher_id: int|null, subjects: list<array{subject_id: int, teacher_id: int|null}>}>  $rows
     * @return list<int>
     */
    private function teacherIds(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = $row['class_teacher_id'];

            foreach ($row['subjects'] as $cell) {
                $ids[] = $cell['teacher_id'];
            }
        }

        return array_values(array_unique(array_filter($ids, fn (?int $id): bool => $id !== null)));
    }

    /**
     * @param  list<array{section_id: int, class_teacher_id: int|null, subjects: list<array{subject_id: int, teacher_id: int|null}>}>  $rows
     * @param  array<int, mixed>  $sections
     * @param  array<int, mixed>  $subjects
     * @param  array<int, User>  $teachers
     */
    private function ensureValid(array $rows, array $sections, array $subjects, array $teachers): void
    {
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            if (! isset($sections[$row['section_id']])) {
                $errors["sections.{$rowIndex}.section_id"] = 'This classroom is not in this class.';

                continue;
            }

            if ($row['class_teacher_id'] !== null && ! isset($teachers[$row['class_teacher_id']])) {
                $errors["sections.{$rowIndex}.class_teacher_id"] = 'Choose a teacher.';
            }

            foreach ($row['subjects'] as $columnIndex => $cell) {
                $key = "sections.{$rowIndex}.subjects.{$columnIndex}.teacher_id";

                if (! isset($subjects[$cell['subject_id']])) {
                    $errors["sections.{$rowIndex}.subjects.{$columnIndex}.subject_id"] = 'This subject is not offered to this class.';

                    continue;
                }

                if ($cell['teacher_id'] === null) {
                    continue;
                }

                $teacher = $teachers[$cell['teacher_id']] ?? null;

                if ($teacher === null || ! $teacher->subjects->contains('id', $cell['subject_id'])) {
                    $errors[$key] = 'This teacher does not teach this subject.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
