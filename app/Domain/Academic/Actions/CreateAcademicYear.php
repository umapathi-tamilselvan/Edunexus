<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates an academic year. The first year created becomes current.
 */
class CreateAcademicYear
{
    public function __construct(private MakeAcademicYearCurrent $makeCurrent) {}

    public function handle(string $name, Carbon $startsOn, Carbon $endsOn, bool $makeCurrent = false): AcademicYear
    {
        return DB::transaction(function () use ($name, $startsOn, $endsOn, $makeCurrent): AcademicYear {
            $isFirstYear = ! AcademicYear::query()->exists();

            $year = AcademicYear::query()->create([
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);

            return $makeCurrent || $isFirstYear ? $this->makeCurrent->handle($year) : $year;
        });
    }
}
