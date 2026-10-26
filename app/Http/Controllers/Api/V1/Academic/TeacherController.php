<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\TeacherWorkload;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Support\AcademicYearContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Teachers for pickers and planning: everyone with the Teacher role or a
 * subject they teach.
 */
class TeacherController extends Controller
{
    /**
     * Every teacher with their subjects, unpaginated, for choosing a class,
     * subject or timetable teacher.
     */
    public function index(): JsonResponse
    {
        $teachers = User::query()
            ->where(fn (Builder $users) => $users->role(Role::Teacher->value)->orWhereHas('subjects'))
            ->with('subjects:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $teachers->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'subjects' => $teacher->subjects->map(fn ($subject): array => ['id' => $subject->id, 'name' => $subject->name])->values(),
            ]),
        ]);
    }

    /**
     * Each teacher's periods per weekday and per week, and section subjects
     * taught, in the selected year.
     */
    public function workload(AcademicYearContext $academicYear, TeacherWorkload $teacherWorkload): JsonResponse
    {
        $year = $academicYear->year();

        return response()->json([
            'data' => $year === null
                ? ['academic_year' => null, 'periods_per_day' => 0, 'teachers' => []]
                : ['academic_year' => ['id' => $year->id, 'name' => $year->name], ...$teacherWorkload->handle($year)],
        ]);
    }
}
