<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Support\Sequences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records an admission enquiry for a class. Application numbers are
 * gapless per academic year, e.g. "2026-27/0007".
 */
class CreateApplication
{
    public function __construct(private Sequences $sequences) {}

    /**
     * @param  array<string, mixed>  $details  Applicant and guardian details.
     */
    public function handle(SchoolClass $class, array $details): AdmissionApplication
    {
        return DB::transaction(function () use ($class, $details): AdmissionApplication {
            $year = $class->academicYear;

            // Refused before a number is drawn, so no gap appears in the numbering (D50).
            if (! $year->admissions_open) {
                throw ValidationException::withMessages([
                    'class_id' => "Admissions are closed for {$year->name}. An admin can open them on the Academic page.",
                ]);
            }

            $number = $this->sequences->next("admission.application.{$year->id}");

            $application = new AdmissionApplication([...$details, 'class_id' => $class->id]);
            $application->academic_year_id = $year->id;
            $application->application_no = $year->name.'/'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $application->status = ApplicationStatus::Enquiry;
            $application->save();

            return $application->load(['academicYear', 'schoolClass']);
        });
    }
}
