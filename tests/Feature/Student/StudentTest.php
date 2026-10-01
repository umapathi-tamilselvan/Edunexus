<?php

namespace Tests\Feature\Student;

use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentTest extends TestCase
{
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'admission_no' => 'ADM001',
            'name' => 'Aarav Kumar',
            'date_of_birth' => '2015-04-10',
            'gender' => 'male',
            'guardians' => [
                ['name' => 'Ravi Kumar', 'phone' => '+91 98765 43210', 'relation' => 'father'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_student_is_created_with_guardians_and_an_enrollment(): void
    {
        $section = Section::factory()->create();

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload([
            'guardians' => [
                ['name' => 'Ravi Kumar', 'phone' => '9876543210', 'relation' => 'father'],
                ['name' => 'Priya Kumar', 'phone' => '9876500000', 'relation' => 'mother', 'is_primary' => true],
            ],
            'enrollment' => ['section_id' => $section->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.admission_no', 'ADM001')
            ->assertJsonPath('data.enrollments.0.section.id', $section->id)
            ->assertJsonPath('data.enrollments.0.roll_no', 1)
            ->assertJsonPath('data.enrollments.0.status', 'active');

        $primary = collect($response->json('data.guardians'))->where('is_primary', true)->pluck('name')->all();
        $this->assertSame(['Priya Kumar'], $primary);
    }

    public function test_a_failed_enrollment_creates_no_student_or_guardian(): void
    {
        $full = Section::factory()->create(['capacity' => 1]);
        StudentEnrollment::factory()->create(['section_id' => $full->id, 'roll_no' => 1]);

        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload(['enrollment' => ['section_id' => $full->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section_id']);

        $this->assertFalse(Student::query()->where('admission_no', 'ADM001')->exists());
        $this->assertSame(0, Guardian::query()->where('phone_hash', Guardian::hashPhone('9876543210'))->count());
    }

    public function test_guardian_phones_are_encrypted_at_rest_and_normalised(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload())->assertCreated();

        $raw = DB::table('guardians')->first();
        $this->assertStringNotContainsString('9876543210', $raw->phone);
        $this->assertSame('9876543210', Guardian::query()->sole()->phone);
        $this->assertSame(Guardian::hashPhone('09876543210'), $raw->phone_hash);
    }

    public function test_siblings_share_one_guardian_record_found_by_phone(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload())->assertCreated();
        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload([
            'admission_no' => 'ADM002',
            'name' => 'Diya Kumar',
            'guardians' => [['name' => 'R. Kumar', 'phone' => '98765-43210', 'relation' => 'father']],
        ]))->assertCreated();

        $this->assertSame(1, Guardian::query()->count());
        $this->assertSame(2, Guardian::query()->sole()->students()->count());
    }

    public function test_creating_a_student_validates_its_input(): void
    {
        Student::factory()->create(['admission_no' => 'ADM001']);

        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload([
            'date_of_birth' => now()->addDay()->toDateString(),
            'gender' => 'unknown',
            'guardians' => [['name' => '', 'phone' => '12345', 'relation' => 'uncle']],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'admission_no' => 'The admission no has already been taken.',
                'date_of_birth' => 'The date of birth field must be a date before today.',
                'gender' => 'The selected gender is invalid.',
                'guardians.0.name' => 'The guardian name field is required.',
                'guardians.0.phone' => 'The guardian phone must be a 10-digit Indian mobile number.',
                'guardians.0.relation' => 'The selected guardian relation is invalid.',
            ]);
    }

    public function test_a_student_needs_at_least_one_guardian(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/students', $this->payload(['guardians' => []]))
            ->assertJsonValidationErrors(['guardians' => 'The guardians field is required.']);
    }

    public function test_a_guardian_can_be_linked_later_and_made_primary(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->adminToken)->postJson("/api/v1/students/{$student->id}/guardians", ['name' => 'Ravi', 'phone' => '9876543210', 'relation' => 'father'])
            ->assertCreated()->assertJsonPath('data.is_primary', true);
        $this->withToken($this->adminToken)->postJson("/api/v1/students/{$student->id}/guardians", ['name' => 'Priya', 'phone' => '9876500000', 'relation' => 'mother', 'is_primary' => true])
            ->assertCreated()->assertJsonPath('data.is_primary', true);

        $this->assertSame(['Priya'], $student->guardians()->wherePivot('is_primary', true)->pluck('name')->all());

        $this->withToken($this->adminToken)->postJson("/api/v1/students/{$student->id}/guardians", ['name' => 'Ravi', 'phone' => '9876543210', 'relation' => 'father'])
            ->assertJsonValidationErrors(['phone' => 'This guardian is already linked to the student.']);
    }

    public function test_removing_the_primary_guardian_promotes_the_next_and_the_last_cannot_be_removed(): void
    {
        $student = Student::factory()->create();
        [$father, $mother] = Guardian::factory()->count(2)->create();
        $student->guardians()->attach($father->id, ['relation' => 'father', 'is_primary' => true]);
        $student->guardians()->attach($mother->id, ['relation' => 'mother', 'is_primary' => false]);

        $this->withToken($this->adminToken)->deleteJson("/api/v1/students/{$student->id}/guardians/{$father->id}")->assertNoContent();
        $this->assertTrue((bool) $student->guardians()->sole()->pivot->is_primary);

        $this->withToken($this->adminToken)->deleteJson("/api/v1/students/{$student->id}/guardians/{$mother->id}")
            ->assertJsonValidationErrors(['guardian' => 'A student must keep at least one guardian.']);
    }

    public function test_student_details_can_be_corrected(): void
    {
        $student = Student::factory()->create(['name' => 'Arav']);

        $this->withToken($this->adminToken)->patchJson("/api/v1/students/{$student->id}", ['name' => 'Aarav'])
            ->assertOk()->assertJsonPath('data.name', 'Aarav');
    }

    public function test_the_list_is_searchable_and_filterable_by_section(): void
    {
        $section = Section::factory()->create();
        $enrolled = Student::factory()->create(['name' => 'Zara Khan', 'admission_no' => 'ADM900']);
        $enrolled->enrollments()->create(['academic_year_id' => $section->schoolClass->academic_year_id, 'section_id' => $section->id, 'roll_no' => 1]);
        Student::factory()->create(['name' => 'Anil Rao']);

        $this->withToken($this->adminToken)->getJson('/api/v1/students?search=zara')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.admission_no', 'ADM900');
        $this->withToken($this->adminToken)->getJson('/api/v1/students?search=ADM9')
            ->assertJsonCount(1, 'data');
        $this->withToken($this->adminToken)->getJson("/api/v1/students?section_id={$section->id}")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.enrollments.0.section.id', $section->id);
        $this->withToken($this->adminToken)->getJson('/api/v1/students')
            ->assertJsonCount(2, 'data');
    }

    public function test_changes_to_students_are_audited(): void
    {
        $student = Student::factory()->create(['name' => 'Arav']);

        $this->withToken($this->adminToken)->patchJson("/api/v1/students/{$student->id}", ['name' => 'Aarav'])->assertOk();

        $this->assertDatabaseHas('audits', ['auditable_type' => Student::class, 'auditable_id' => $student->id, 'event' => 'updated']);
    }
}
