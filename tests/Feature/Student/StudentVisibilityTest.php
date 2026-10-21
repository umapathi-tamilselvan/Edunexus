<?php

namespace Tests\Feature\Student;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Teachers see only students in sections they are class teacher of;
 * roles with student.view (e.g. Accountant) see everyone.
 */
class StudentVisibilityTest extends TestCase
{
    private User $teacher;

    private Student $ownStudent;

    private Student $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::factory()->current()->create();
        $class = SchoolClass::factory()->for($year)->create();
        $this->teacher = $this->createUser(Role::Teacher);

        $ownSection = Section::factory()->for($class, 'schoolClass')->create(['class_teacher_id' => $this->teacher->id]);
        $otherSection = Section::factory()->for($class, 'schoolClass')->create();

        $this->ownStudent = StudentEnrollment::factory()->create(['section_id' => $ownSection->id, 'roll_no' => 1])->student;
        $this->otherStudent = StudentEnrollment::factory()->create(['section_id' => $otherSection->id, 'roll_no' => 1])->student;
    }

    public function test_a_teacher_lists_only_students_of_their_own_sections(): void
    {
        $this->withToken($this->issueToken($this->teacher->email))->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ownStudent->id);
    }

    public function test_a_teacher_cannot_open_a_student_of_another_section(): void
    {
        $token = $this->issueToken($this->teacher->email);

        $this->withToken($token)->getJson("/api/v1/students/{$this->ownStudent->id}")->assertOk();
        $this->withToken($token)->getJson("/api/v1/students/{$this->otherStudent->id}")->assertForbidden();
    }

    public function test_a_teacher_sees_guardian_phones_masked(): void
    {
        $this->ownStudent->guardians()->attach(
            Guardian::factory()->create(['phone' => '9876543210'])->id,
            ['relation' => 'father', 'is_primary' => true],
        );

        $this->withToken($this->issueToken($this->teacher->email))->getJson("/api/v1/students/{$this->ownStudent->id}")
            ->assertJsonPath('data.guardians.0.phone', '••••••3210');
    }

    #[TestWith([Role::Accountant])]
    #[TestWith([Role::Principal])]
    #[TestWith([Role::Admin])]
    public function test_roles_with_student_view_see_every_student(Role $role): void
    {
        $this->withToken($this->issueToken($this->createUser($role)->email))->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_guardian_logins_cannot_list_students(): void
    {
        $this->withToken($this->issueToken($this->createUser(Role::Guardian)->email))->getJson('/api/v1/students')->assertForbidden();
    }
}
