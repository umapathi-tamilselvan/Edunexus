<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Support\OrganizationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the academic year that starts in the client's start month of a
 * calendar year: with June, 2026 gives "2026-27", from 1 June 2026 to
 * 31 May 2027 (with January, "2026"). A year never overlaps another: it
 * starts after the year before it ends and ends before the year after it
 * starts. The first year created becomes current.
 */
class CreateAcademicYear
{
    public function __construct(
        private MakeAcademicYearCurrent $makeCurrent,
        private OrganizationContext $organization,
    ) {}

    public function handle(int $startYear): AcademicYear
    {
        $month = $this->organization->yearStartMonth();
        $name = $month === 1 ? (string) $startYear : sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);

        return DB::transaction(function () use ($startYear, $month, $name): AcademicYear {
            // Lock all years so a concurrent creation cannot overlap this one.
            $isFirstYear = AcademicYear::query()->lockForUpdate()->get(['id'])->isEmpty();

            if (AcademicYear::query()->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['academic_year' => "The academic year {$name} already exists."]);
            }

            $startsOn = Carbon::create($startYear, $month, 1);
            $endsOn = $startsOn->copy()->addYear()->subDay();

            $previousEndsOn = AcademicYear::query()->where('starts_on', '<=', $startsOn)->max('ends_on');
            $nextStartsOn = AcademicYear::query()->where('starts_on', '>', $startsOn)->min('starts_on');

            if ($previousEndsOn !== null && $startsOn->lte($previousEndsOn)) {
                $startsOn = Carbon::parse($previousEndsOn)->addDay();
            }

            if ($nextStartsOn !== null && $endsOn->gte($nextStartsOn)) {
                $endsOn = Carbon::parse($nextStartsOn)->subDay();
            }

            if ($startsOn->gt($endsOn)) {
                throw ValidationException::withMessages(['academic_year' => "The academic year {$name} has no days left between the years around it."]);
            }

            $year = AcademicYear::query()->create(['name' => $name, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);

            return $isFirstYear ? $this->makeCurrent->handle($year) : $year;
        });
    }

    /**
     * The calendar year in which the academic year holding the date started,
     * e.g. 2026 for 26 October 2026 and for 15 March 2027 with June.
     */
    public function startYearOf(CarbonInterface $date): int
    {
        return $date->month >= $this->organization->yearStartMonth() ? $date->year : $date->year - 1;
    }
}
