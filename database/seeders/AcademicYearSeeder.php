<?php

namespace Database\Seeders;

use App\Domain\Academic\Actions\CreateAcademicYear;
use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Database\Seeder;

/**
 * Opens a client's first academic year, the one today falls in, when it
 * has none. The first year becomes current; later years come from
 * POST academic-years/next, so no one creates years by hand.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(CreateAcademicYear $createAcademicYear): void
    {
        if (AcademicYear::query()->exists()) {
            return;
        }

        $createAcademicYear->handle($createAcademicYear->startYearOf(today()));
    }
}
