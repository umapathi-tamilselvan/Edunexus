<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Actions\SetTimetableSlot;
use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\SetTimetableSlotRequest;
use App\Http\Resources\Api\V1\PeriodResource;
use App\Http\Resources\Api\V1\TimetableEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimetableController extends Controller
{
    /**
     * A section's weekly timetable with its year's periods, and who teaches
     * each subject to the section.
     */
    public function section(Section $section): JsonResponse
    {
        $section->load(['schoolClass.academicYear', 'subjectTeachers.teacher']);

        return response()->json([
            'data' => [
                'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
                'periods' => PeriodResource::collection($section->schoolClass->academicYear->periods()->get()),
                'entries' => TimetableEntryResource::collection($section->timetableEntries()->with(['subject', 'teacher'])->get()),
                'subject_teachers' => $section->subjectTeachers->map(fn (SectionSubjectTeacher $assignment): array => [
                    'subject_id' => $assignment->subject_id,
                    'teacher' => ['id' => $assignment->teacher->id, 'name' => $assignment->teacher->name],
                ])->values(),
            ],
        ]);
    }

    /**
     * The signed-in user's teaching timetable in the current year.
     */
    public function mine(Request $request): JsonResponse
    {
        $entries = TimetableEntry::query()
            ->where('teacher_id', $request->user()->id)
            ->whereHas('period.academicYear', fn ($year) => $year->where('is_current', true))
            ->with(['period', 'subject', 'section.schoolClass'])
            ->get();

        return response()->json([
            'data' => [
                'periods' => PeriodResource::collection($entries->pluck('period')->unique('id')->sortBy('sort_order')->values()),
                'entries' => TimetableEntryResource::collection($entries),
            ],
        ]);
    }

    public function set(SetTimetableSlotRequest $request, Section $section, int $weekday, Period $period, SetTimetableSlot $setSlot): TimetableEntryResource
    {
        return new TimetableEntryResource($setSlot->handle(
            $section,
            Weekday::from($weekday),
            $period,
            Subject::query()->findOrFail($request->validated('subject_id')),
            $request->validated('teacher_id') ? User::query()->findOrFail($request->validated('teacher_id')) : null,
        ));
    }

    public function clear(Section $section, int $weekday, Period $period): Response
    {
        $section->timetableEntries()->where('weekday', $weekday)->where('period_id', $period->id)->delete();

        return response()->noContent();
    }
}
