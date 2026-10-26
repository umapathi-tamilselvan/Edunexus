<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Support\AcademicYearContext;
use Illuminate\Support\Facades\DB;

/**
 * Makes one academic year current and every other year not current.
 */
class MakeAcademicYearCurrent
{
    public function __construct(private AcademicYearContext $academicYear) {}

    public function handle(AcademicYear $year): AcademicYear
    {
        DB::transaction(function () use ($year): void {
            // Lock all years so two concurrent switches cannot leave two current.
            AcademicYear::query()->lockForUpdate()->get(['id']);

            AcademicYear::query()->whereKeyNot($year->getKey())->where('is_current', true)->update(['is_current' => false]);
            AcademicYear::query()->whereKey($year->getKey())->update(['is_current' => true]);

            // Bulk updates fire no model events, so the cached years are forgotten here.
            $this->academicYear->forgetYears();
        });

        return $year->refresh();
    }
}
