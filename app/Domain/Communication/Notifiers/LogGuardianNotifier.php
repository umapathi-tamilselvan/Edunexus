<?php

namespace App\Domain\Communication\Notifiers;

use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * The default driver: writes the notice to the application log and sends
 * nothing. The phone number is left out of the log.
 */
class LogGuardianNotifier implements GuardianNotifier
{
    public function absent(Guardian $guardian, Student $student, CarbonInterface $date, string $when): void
    {
        Log::info('Absence notice (log driver, nothing sent).', [
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'date' => $date->toDateString(),
            'when' => $when,
        ]);
    }
}
