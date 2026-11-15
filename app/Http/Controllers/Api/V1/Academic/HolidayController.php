<?php

namespace App\Http\Controllers\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Holiday;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\StoreHolidayRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Days of an academic year without classes. No attendance is taken on
 * them, so they never count against a student.
 */
class HolidayController extends Controller
{
    public function index(AcademicYear $academicYear): JsonResponse
    {
        return response()->json([
            'data' => $academicYear->holidays()->get()->map(fn (Holiday $holiday): array => self::present($holiday)),
        ]);
    }

    /**
     * A day already marked can't become a holiday: clear its attendance first.
     */
    public function store(StoreHolidayRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $date = Carbon::parse($request->validated('date'));

        if (! $academicYear->includes($date)) {
            throw ValidationException::withMessages(['date' => "{$date->format('j M Y')} is not in {$academicYear->name}."]);
        }

        $marked = AttendanceRecord::query()
            ->where('date', $date->toDateString())
            ->whereHas('enrollment', fn ($enrollments) => $enrollments->where('academic_year_id', $academicYear->id))
            ->exists();

        if ($marked) {
            throw ValidationException::withMessages(['date' => "Attendance has been taken on {$date->format('j M Y')}. Clear it before making the day a holiday."]);
        }

        $holiday = $academicYear->holidays()->create($request->validated());

        return response()->json(['data' => self::present($holiday)], 201);
    }

    public function destroy(Holiday $holiday): Response
    {
        $holiday->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, date: string, name: string}
     */
    private static function present(Holiday $holiday): array
    {
        return ['id' => $holiday->id, 'date' => $holiday->date->toDateString(), 'name' => $holiday->name];
    }
}
