<?php

namespace Tests\Feature\Student;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
    }

    private function enroll(Student $student, Section $section, ?int $rollNo = null): TestResponse
    {
        return $this->withToken($this->adminToken)->postJson("/api/v1/students/{$student->id}/enrollments", array_filter(['section_id' => $section->id, 'roll_no' => $rollNo]));
    }

    public function test_enrolling_assigns_the_sections_year_and_the_next_roll_number(): void
    {
        $section = Section::factory()->create();

        $this->enroll(Student::factory()->create(), $section)->assertCreated()->assertJsonPath('data.roll_no', 1);
        $this->enroll(Student::factory()->create(), $section, 7)->assertCreated()->assertJsonPath('data.roll_no', 7);
        $this->enroll(Student::factory()->create(), $section)->assertCreated()
            ->assertJsonPath('data.roll_no', 8)
            ->assertJsonPath('data.academic_year.id', $section->schoolClass->academic_year_id);
    }

    public function test_a_student_is_enrolled_only_once_per_academic_year(): void
    {
        $student = Student::factory()->create();
        $sectionA = Section::factory()->create();
        $sectionB = Section::factory()->for($sectionA->schoolClass, 'schoolClass')->create();

        $this->enroll($student, $sectionA)->assertCreated();
        $this->enroll($student, $sectionB)->assertJsonValidationErrors(['section_id' => 'This student is already enrolled in this academic year.']);
    }

    public function test_a_full_section_refuses_new_students(): void
    {
        $section = Section::factory()->create(['capacity' => 1]);
        $this->enroll(Student::factory()->create(), $section)->assertCreated();

        $this->enroll(Student::factory()->create(), $section)
            ->assertJsonValidationErrors(['section_id' => "Section {$section->schoolClass->name} {$section->name} is full (1 students)."]);
    }

    public function test_roll_numbers_are_unique_within_a_section(): void
    {
        $section = Section::factory()->create();
        $this->enroll(Student::factory()->create(), $section, 3)->assertCreated();

        $this->enroll(Student::factory()->create(), $section, 3)
            ->assertJsonValidationErrors(['roll_no' => 'Roll number 3 is already taken in this section.']);
    }

    public function test_an_enrollment_moves_to_another_section_of_the_same_year_with_a_new_roll_number(): void
    {
        $from = Section::factory()->create();
        $to = Section::factory()->for($from->schoolClass, 'schoolClass')->create();
        StudentEnrollment::factory()->create(['section_id' => $to->id, 'roll_no' => 4]);
        $enrollment = StudentEnrollment::factory()->create(['section_id' => $from->id, 'roll_no' => 1]);

        $this->withToken($this->adminToken)->patchJson("/api/v1/enrollments/{$enrollment->id}", ['section_id' => $to->id])
            ->assertOk()
            ->assertJsonPath('data.section.id', $to->id)
            ->assertJsonPath('data.roll_no', 5);
    }

    public function test_an_enrollment_cannot_move_to_another_academic_year(): void
    {
        $enrollment = StudentEnrollment::factory()->create();
        $otherYearSection = Section::factory()->for(SchoolClass::factory(), 'schoolClass')->create();

        $this->withToken($this->adminToken)->patchJson("/api/v1/enrollments/{$enrollment->id}", ['section_id' => $otherYearSection->id])
            ->assertJsonValidationErrors(['section_id' => 'An enrollment can only move to a section in the same academic year. Use promotion to change years.']);
    }

    public function test_the_roll_number_can_be_changed_or_cleared(): void
    {
        $enrollment = StudentEnrollment::factory()->create(['roll_no' => 1]);

        $this->withToken($this->adminToken)->patchJson("/api/v1/enrollments/{$enrollment->id}", ['roll_no' => 12])
            ->assertOk()->assertJsonPath('data.roll_no', 12);
        $this->withToken($this->adminToken)->patchJson("/api/v1/enrollments/{$enrollment->id}", ['roll_no' => null])
            ->assertOk()->assertJsonPath('data.roll_no', null);
    }

    public function test_enrollment_changes_need_student_management(): void
    {
        $section = Section::factory()->create();
        $token = $this->issueToken($this->createUser(Role::Teacher)->email);

        $this->withToken($token)->postJson('/api/v1/students/'.Student::factory()->create()->id.'/enrollments', ['section_id' => $section->id])
            ->assertForbidden();
    }
}
