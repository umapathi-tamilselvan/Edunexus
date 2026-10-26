<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens the academic year after the current one and copies the current
 * year's structure into it: its classes, their sections and capacities,
 * the subjects each class studies, and the bell schedule. Class teachers,
 * subject teachers and the timetable are set afresh for the new year. The
 * new year becomes current only when someone makes it so.
 */
class CopyAcademicYearForward
{
    public function __construct(private CreateAcademicYear $createAcademicYear) {}

    public function handle(): AcademicYear
    {
        return DB::transaction(function (): AcademicYear {
            $current = AcademicYear::query()->current()->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['academic_year' => 'There is no current academic year to copy from.']);

            $later = AcademicYear::query()->where('starts_on', '>', $current->starts_on)->orderBy('starts_on')->first();

            if ($later !== null) {
                throw ValidationException::withMessages(['academic_year' => "The next academic year, {$later->name}, already exists."]);
            }

            $next = $this->createAcademicYear->handle($current->starts_on->year + 1);

            foreach ($current->classes()->with(['sections', 'subjects'])->get() as $class) {
                $copy = $next->classes()->create($class->only(['name', 'sort_order']));
                $copy->sections()->createMany($class->sections->map(fn (Section $section): array => $section->only(['name', 'capacity']))->all());
                $copy->subjects()->attach($class->subjects->modelKeys());
            }

            $next->periods()->createMany($current->periods()->get()->map(fn (Period $period): array => $period->only(['name', 'starts_at', 'ends_at', 'is_break', 'sort_order']))->all());

            return $next;
        });
    }
}
