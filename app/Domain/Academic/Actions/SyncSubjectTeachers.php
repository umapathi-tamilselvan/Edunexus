<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the teachers who teach a subject: each teacher added or removed has
 * their own subjects changed, under the same rule (SyncTeacherSubjects).
 */
class SyncSubjectTeachers
{
    public function __construct(private SyncTeacherSubjects $syncTeacherSubjects) {}

    /**
     * @param  list<int>  $teacherIds
     */
    public function handle(Subject $subject, array $teacherIds): Subject
    {
        try {
            DB::transaction(function () use ($subject, $teacherIds): void {
                $currentIds = $subject->teachers()->pluck('users.id')->all();
                $changedIds = [...array_diff($teacherIds, $currentIds), ...array_diff($currentIds, $teacherIds)];

                foreach (User::query()->with('subjects')->findMany($changedIds) as $teacher) {
                    $subjectIds = array_diff($teacher->subjects->modelKeys(), [$subject->id]);

                    if (in_array($teacher->id, $teacherIds, true)) {
                        $subjectIds[] = $subject->id;
                    }

                    $this->syncTeacherSubjects->handle($teacher, array_values($subjectIds));
                }
            });
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['teacher_ids' => Arr::flatten($exception->errors())]);
        }

        return $subject->load('teachers:id,name');
    }
}
