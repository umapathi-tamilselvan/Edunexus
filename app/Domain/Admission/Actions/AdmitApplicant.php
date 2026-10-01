<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Actions\CreateStudent;
use App\Domain\Student\Models\GuardianConsent;
use App\Domain\Student\Models\Student;
use App\Support\OrganizationContext;
use App\Support\Sequences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admits an applicant: creates the student with their guardian, enrolls
 * them in a section of the class they applied for, and records the
 * guardian's consent (DPDP Act) against the current privacy notice.
 */
class AdmitApplicant
{
    public function __construct(
        private CreateStudent $createStudent,
        private Sequences $sequences,
        private OrganizationContext $organization,
    ) {}

    public function handle(
        AdmissionApplication $application,
        Section $section,
        string $consentMethod,
        User $actor,
        ?string $admissionNo = null,
        ?int $rollNo = null,
        ?string $ipAddress = null,
    ): AdmissionApplication {
        return DB::transaction(function () use ($application, $section, $consentMethod, $actor, $admissionNo, $rollNo, $ipAddress): AdmissionApplication {
            $application = AdmissionApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $application->status->canBecome(ApplicationStatus::Admitted)) {
                throw ValidationException::withMessages([
                    'status' => "A {$application->status->value} application cannot be admitted.",
                ]);
            }

            if ($section->class_id !== $application->class_id) {
                throw ValidationException::withMessages([
                    'section_id' => 'Choose a section of the class the student applied for.',
                ]);
            }

            $student = $this->createStudent->handle(
                admissionNo: $admissionNo ?? $this->nextAdmissionNo(),
                name: $application->student_name,
                dateOfBirth: $application->date_of_birth,
                gender: $application->gender,
                guardians: [[
                    'name' => $application->guardian_name,
                    'phone' => $application->guardian_phone,
                    'email' => $application->guardian_email,
                    'relation' => $application->guardian_relation->value,
                    'is_primary' => true,
                ]],
                enrollment: ['section_id' => $section->id, 'roll_no' => $rollNo],
            );

            GuardianConsent::query()->create([
                'guardian_id' => $student->guardians->firstOrFail()->id,
                'student_id' => $student->id,
                'notice_version' => (string) config('edunexus.privacy_notice_version'),
                'method' => $consentMethod,
                'consented_at' => now(),
                'recorded_by' => $actor->id,
                'ip_address' => $ipAddress,
            ]);

            $application->status = ApplicationStatus::Admitted;
            $application->student_id = $student->id;
            $application->decided_by = $actor->id;
            $application->decided_at = now();
            $application->save();

            return $application->load(['academicYear', 'schoolClass', 'decidedBy', 'student']);
        });
    }

    /**
     * Next free admission number, e.g. "ABC00042". Numbers typed in by hand
     * earlier are skipped.
     */
    private function nextAdmissionNo(): string
    {
        do {
            $admissionNo = strtoupper($this->organization->code()).str_pad((string) $this->sequences->next('student.admission_no'), 5, '0', STR_PAD_LEFT);
        } while (Student::query()->where('admission_no', $admissionNo)->exists());

        return $admissionNo;
    }
}
