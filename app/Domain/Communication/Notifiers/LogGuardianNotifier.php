<?php

namespace App\Domain\Communication\Notifiers;

use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * The default driver: writes to the application log and sends nothing. The
 * phone number is left out of the log. A sign-in code is written to the log
 * only in local and testing environments, so developers can sign in without
 * a phone; elsewhere the log says a code was requested and not the code.
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

    public function canSend(): bool
    {
        return false;
    }

    public function otp(Guardian $guardian, string $code): void
    {
        $context = ['guardian_id' => $guardian->id];

        if (app()->environment('local', 'testing')) {
            $context['code'] = $code;
        }

        Log::info('Guardian sign-in code (log driver, nothing sent).', $context);
    }
}
