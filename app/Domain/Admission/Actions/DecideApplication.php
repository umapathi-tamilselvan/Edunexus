<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Approves, rejects or withdraws an open application. Admitting is
 * AdmitApplicant, because it creates the student.
 */
class DecideApplication
{
    public function handle(AdmissionApplication $application, ApplicationStatus $decision, User $actor, ?string $reason = null): AdmissionApplication
    {
        if (! in_array($decision, [ApplicationStatus::Approved, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn], true)) {
            throw new InvalidArgumentException("Use AdmitApplicant to admit; [{$decision->value}] is not a decision.");
        }

        return DB::transaction(function () use ($application, $decision, $actor, $reason): AdmissionApplication {
            $application = AdmissionApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $application->status->canBecome($decision)) {
                throw ValidationException::withMessages([
                    'status' => "A {$application->status->value} application cannot be {$decision->value}.",
                ]);
            }

            $application->status = $decision;
            $application->decision_reason = $reason;
            $application->decided_by = $actor->id;
            $application->decided_at = now();
            $application->save();

            return $application->load(['academicYear', 'schoolClass', 'decidedBy']);
        });
    }
}
