<?php

namespace Tests\Feature\Admission;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\GuardianConsent;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    private string $token;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->class = SchoolClass::factory()->for(AcademicYear::factory()->current()->create(['name' => '2026-27']))->create(['name' => 'Class 1']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function enquiry(array $overrides = []): array
    {
        return [
            'class_id' => $this->class->id,
            'student_name' => 'Ishaan Mehta',
            'date_of_birth' => '2020-07-15',
            'gender' => 'male',
            'previous_school' => 'Little Stars Playschool',
            'guardian_name' => 'Neha Mehta',
            'guardian_phone' => '+91 99887 76655',
            'guardian_relation' => 'mother',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admit(AdmissionApplication $application, Section $section, array $overrides = []): TestResponse
    {
        return $this->withToken($this->token)->postJson("/api/v1/admissions/{$application->id}/admit", [
            'section_id' => $section->id,
            'consent_given' => true,
            'consent_method' => 'signed_form',
            ...$overrides,
        ]);
    }

    public function test_an_enquiry_gets_a_gapless_application_number_per_year(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admissions', $this->enquiry())
            ->assertCreated()
            ->assertJsonPath('data.application_no', '2026-27/0001')
            ->assertJsonPath('data.status', 'enquiry')
            ->assertJsonPath('data.class.name', 'Class 1');

        $this->withToken($this->token)->postJson('/api/v1/admissions', $this->enquiry(['guardian_phone' => '9000000000']))
            ->assertJsonPath('data.application_no', '2026-27/0002');
    }

    public function test_the_guardian_phone_is_encrypted_on_the_application(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admissions', $this->enquiry())->assertCreated();

        $raw = DB::table('admission_applications')->first();
        $this->assertStringNotContainsString('9988776655', $raw->guardian_phone);
        $this->assertSame('9988776655', AdmissionApplication::query()->sole()->guardian_phone);
    }

    public function test_an_enquiry_is_validated(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admissions', $this->enquiry(['student_name' => '', 'guardian_phone' => '123', 'gender' => 'x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_name' => 'The student name field is required.',
                'guardian_phone' => 'The guardian phone must be a 10-digit Indian mobile number.',
                'gender' => 'The selected gender is invalid.',
            ]);
    }

    public function test_admitting_creates_the_student_guardian_enrollment_and_consent(): void
    {
        $application = AdmissionApplication::factory()->approved()->create(['class_id' => $this->class->id, 'student_name' => 'Ishaan Mehta', 'guardian_phone' => '9988776655']);
        $section = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'A']);

        $response = $this->admit($application, $section)
            ->assertOk()
            ->assertJsonPath('data.status', 'admitted')
            ->assertJsonPath('data.student.admission_no', 'TEST00001');

        $student = Student::query()->findOrFail($response->json('data.student.id'));
        $this->assertSame('Ishaan Mehta', $student->name);
        $this->assertSame($section->id, $student->enrollments()->sole()->section_id);
        $this->assertSame('9988776655', $student->guardians()->sole()->phone);

        $consent = GuardianConsent::query()->sole();
        $this->assertSame($student->id, $consent->student_id);
        $this->assertSame('2026-10', $consent->notice_version);
        $this->assertSame('signed_form', $consent->method);
        $this->assertNotNull($consent->recorded_by);
    }

    public function test_an_enquiry_can_be_admitted_directly_with_a_typed_admission_number(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);
        $section = Section::factory()->for($this->class, 'schoolClass')->create();

        $this->admit($application, $section, ['admission_no' => 'OLD-1999', 'roll_no' => 12])
            ->assertOk()
            ->assertJsonPath('data.student.admission_no', 'OLD-1999');

        $this->assertSame(12, StudentEnrollment::query()->sole()->roll_no);
    }

    public function test_generated_admission_numbers_skip_ones_already_in_use(): void
    {
        Student::factory()->create(['admission_no' => 'TEST00001']);
        $section = Section::factory()->for($this->class, 'schoolClass')->create();

        $this->admit(AdmissionApplication::factory()->create(['class_id' => $this->class->id]), $section)
            ->assertJsonPath('data.student.admission_no', 'TEST00002');
    }

    public function test_a_sibling_admission_reuses_the_existing_guardian(): void
    {
        $section = Section::factory()->for($this->class, 'schoolClass')->create();
        $this->admit(AdmissionApplication::factory()->create(['class_id' => $this->class->id, 'guardian_phone' => '9988776655']), $section)->assertOk();
        $this->admit(AdmissionApplication::factory()->create(['class_id' => $this->class->id, 'guardian_phone' => '+91-99887-76655']), $section)->assertOk();

        $this->assertSame(1, Guardian::query()->count());
        $this->assertSame(2, Guardian::query()->sole()->students()->count());
    }

    public function test_admission_requires_the_guardians_consent(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);
        $section = Section::factory()->for($this->class, 'schoolClass')->create();

        $this->admit($application, $section, ['consent_given' => false, 'consent_method' => 'telepathy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'consent_given' => 'The guardian\'s consent to the privacy notice is required to admit a student.',
                'consent_method' => 'The selected consent method is invalid.',
            ]);

        $this->assertSame(0, Student::query()->count());
    }

    public function test_the_section_must_belong_to_the_class_applied_for(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);
        $otherClassSection = Section::factory()->for(SchoolClass::factory()->for($this->class->academicYear), 'schoolClass')->create();

        $this->admit($application, $otherClassSection)
            ->assertJsonValidationErrors(['section_id' => 'Choose a section of the class the student applied for.']);
    }

    public function test_a_failed_admission_leaves_nothing_behind(): void
    {
        $section = Section::factory()->for($this->class, 'schoolClass')->create(['capacity' => 1]);
        StudentEnrollment::factory()->create(['section_id' => $section->id, 'roll_no' => 1]);
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id, 'guardian_phone' => '9988776655']);

        $this->admit($application, $section)->assertJsonValidationErrors(['section_id']);

        $this->assertSame(ApplicationStatus::Enquiry, $application->fresh()->status);
        $this->assertSame(0, GuardianConsent::query()->count());
        $this->assertFalse(Guardian::query()->where('phone_hash', Guardian::hashPhone('9988776655'))->exists());

        // The rollback gave the admission number back: the next admission gets it.
        $section->update(['capacity' => 2]);
        $this->admit($application, $section)->assertOk()->assertJsonPath('data.student.admission_no', 'TEST00001');
    }

    public function test_an_admitted_application_cannot_be_admitted_again_or_changed(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);
        $section = Section::factory()->for($this->class, 'schoolClass')->create();
        $this->admit($application, $section)->assertOk();

        $this->admit($application, $section)->assertJsonValidationErrors(['status' => 'A admitted application cannot be admitted.']);
        $this->withToken($this->token)->patchJson("/api/v1/admissions/{$application->id}", ['student_name' => 'X'])
            ->assertJsonValidationErrors(['status' => 'A admitted application can no longer be changed.']);
    }

    public function test_rejecting_needs_a_reason_and_records_who_decided(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);

        $this->withToken($this->token)->postJson("/api/v1/admissions/{$application->id}/decision", ['decision' => 'rejected'])
            ->assertJsonValidationErrors(['reason' => 'Give a reason when rejecting an application.']);

        $this->withToken($this->token)->postJson("/api/v1/admissions/{$application->id}/decision", ['decision' => 'rejected', 'reason' => 'Age criteria not met'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_reason', 'Age criteria not met')
            ->assertJsonPath('data.is_open', false);

        $this->assertNotNull($application->fresh()->decided_by);
    }

    #[TestWith(['rejected', 'approved'])]
    #[TestWith(['withdrawn', 'approved'])]
    #[TestWith(['approved', 'approved'])]
    public function test_closed_or_repeated_decisions_are_refused(string $from, string $decision): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id, 'status' => $from]);

        $this->withToken($this->token)->postJson("/api/v1/admissions/{$application->id}/decision", ['decision' => $decision])
            ->assertJsonValidationErrors(['status' => "A {$from} application cannot be {$decision}."]);
    }

    public function test_an_open_application_can_move_to_another_class_of_the_same_year_only(): void
    {
        $application = AdmissionApplication::factory()->create(['class_id' => $this->class->id]);
        $sameYear = SchoolClass::factory()->for($this->class->academicYear)->create();
        $otherYear = SchoolClass::factory()->create();

        $this->withToken($this->token)->patchJson("/api/v1/admissions/{$application->id}", ['class_id' => $otherYear->id])
            ->assertJsonValidationErrors(['class_id' => 'Choose a class in the same academic year as the application.']);
        $this->withToken($this->token)->patchJson("/api/v1/admissions/{$application->id}", ['class_id' => $sameYear->id])
            ->assertOk()->assertJsonPath('data.class.id', $sameYear->id);
    }

    public function test_applications_are_listed_by_status_and_searchable_by_guardian_phone(): void
    {
        AdmissionApplication::factory()->create(['class_id' => $this->class->id, 'student_name' => 'Ishaan', 'guardian_phone' => '9988776655']);
        AdmissionApplication::factory()->approved()->create(['class_id' => $this->class->id, 'student_name' => 'Diya']);

        $this->withToken($this->token)->getJson('/api/v1/admissions?status=approved')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Diya');
        $this->withToken($this->token)->getJson('/api/v1/admissions?search=99887 76655')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_name', 'Ishaan');
    }

    public function test_an_application_is_shown_with_its_class_year_and_decision(): void
    {
        $application = AdmissionApplication::factory()->create([
            'class_id' => $this->class->id,
            'status' => ApplicationStatus::Rejected,
            'decision_reason' => 'Class is full.',
            'decided_by' => $this->createUser(Role::Principal, ['name' => 'Principal Rao'])->id,
            'decided_at' => now(),
        ]);

        $this->withToken($this->token)->getJson("/api/v1/admissions/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.class.name', 'Class 1')
            ->assertJsonPath('data.academic_year.name', '2026-27')
            ->assertJsonPath('data.decision_reason', 'Class is full.')
            ->assertJsonPath('data.decided_by.name', 'Principal Rao')
            ->assertJsonPath('data.student', null);
    }

    #[TestWith([Role::Teacher])]
    #[TestWith([Role::Accountant])]
    #[TestWith([Role::Guardian])]
    public function test_only_admission_managers_can_see_applications(Role $role): void
    {
        $this->withToken($this->issueToken($this->createUser($role)->email))->getJson('/api/v1/admissions')->assertForbidden();
    }
}
