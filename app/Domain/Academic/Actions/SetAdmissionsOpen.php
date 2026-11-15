<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;

/**
 * Switches a year's admissions on or off (D44). The Year Setup step
 * "Admissions open" is done while it is on. It does not block applications.
 */
class SetAdmissionsOpen
{
    public function handle(AcademicYear $year, bool $open): AcademicYear
    {
        $year->update(['admissions_open' => $open]);

        return $year;
    }
}
