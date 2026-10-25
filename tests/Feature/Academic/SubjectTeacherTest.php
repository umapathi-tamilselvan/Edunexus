<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Who teaches each subject to each section, e.g. Class 1 A Mathematics:
 * Satheesh, and the subject each teacher is linked to.
 */
class SubjectTeacherTest extends TestCase
{
    private string $adminToken;

    private SchoolClass $class;

    private Section $section;

    private Subject $maths;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->class = SchoolClass::factory()->for(AcademicYear::factory()->current())->create(['name' => 'Class 1']);
        $this->section = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'A']);
        $this->maths = Subject::factory()->create(['name' => 'Mathematics']);
        $this->class->subjects()->attach($this->maths);
        $this->teacher = $this->createUser(Role::Teacher, ['name' => 'Satheesh', 'subject_id' => $this->maths->id]);
    }

    public function test_a_section_gets_a_subject_teacher_who_teaches_that_subject(): void
    {
        $this->assign($this->section, $this->maths, $this->teacher)
            ->assertOk()
            ->assertJsonPath('data.teacher.name', 'Satheesh');

        $this->assertDatabaseHas('section_subject_teachers', ['section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id]);
        $this->withToken($this->adminToken)->getJson("/api/v1/sections/{$this->section->id}")
            ->assertJsonPath('data.subject_teachers.0.teacher.name', 'Satheesh');
    }

    public function test_assigning_again_replaces_the_teacher_and_no_teacher_clears_the_subject(): void
    {
        $karthik = $this->createUser(Role::Teacher, ['subject_id' => $this->maths->id]);
        $this->assign($this->section, $this->maths, $this->teacher)->assertOk();

        $this->assign($this->section, $this->maths, $karthik)->assertOk();
        $this->assertSame($karthik->id, SectionSubjectTeacher::query()->sole()->teacher_id);

        $this->assign($this->section, $this->maths, null)
            ->assertOk()
            ->assertJsonPath('data.teacher', null);
        $this->assertDatabaseCount('section_subject_teachers', 0);
    }

    public function test_a_teacher_of_another_subject_or_of_none_is_rejected(): void
    {
        $english = Subject::factory()->create(['name' => 'English']);
        $karthik = $this->createUser(Role::Teacher, ['name' => 'Karthik', 'subject_id' => $english->id]);
        $srini = $this->createUser(Role::Teacher, ['name' => 'Srini']);

        $this->assign($this->section, $this->maths, $karthik)
            ->assertJsonValidationErrors(['teacher_id' => 'Karthik teaches English, not Mathematics.']);
        $this->assign($this->section, $this->maths, $srini)
            ->assertJsonValidationErrors(['teacher_id' => 'Srini is not linked to a subject. Link them to Mathematics on the Users page first.']);
    }

    public function test_the_subject_must_be_offered_to_the_sections_class(): void
    {
        $art = Subject::factory()->create(['name' => 'Art']);

        $this->assign($this->section, $art, null)
            ->assertJsonValidationErrors(['subject_id' => 'Art is not offered to Class 1. Add it to the class\'s subjects first.']);
    }

    public function test_teachers_cannot_assign_subject_teachers(): void
    {
        $this->withToken($this->issueToken($this->teacher->email))
            ->putJson("/api/v1/sections/{$this->section->id}/subjects/{$this->maths->id}/teacher", ['teacher_id' => $this->teacher->id])
            ->assertForbidden();
    }

    public function test_a_teacher_cannot_move_to_another_subject_while_teaching_their_subject_to_sections(): void
    {
        $english = Subject::factory()->create(['name' => 'English']);
        $this->assign($this->section, $this->maths, $this->teacher)->assertOk();

        $this->withToken($this->adminToken)->putJson("/api/v1/users/{$this->teacher->id}/subject", ['subject_id' => $english->id])
            ->assertJsonValidationErrors(['subject_id' => 'Satheesh teaches Mathematics to Class 1 A. Give those sections another teacher first.']);

        $this->assign($this->section, $this->maths, null)->assertOk();
        $this->withToken($this->adminToken)->putJson("/api/v1/users/{$this->teacher->id}/subject", ['subject_id' => $english->id])
            ->assertOk()
            ->assertJsonPath('data.subject.name', 'English');
        $this->assertSame($english->id, $this->teacher->fresh()->subject_id);
    }

    public function test_only_user_managers_link_a_teacher_to_a_subject(): void
    {
        $this->withToken($this->issueToken($this->teacher->email))
            ->putJson("/api/v1/users/{$this->teacher->id}/subject", ['subject_id' => null])
            ->assertForbidden();
    }

    public function test_taking_a_subject_off_a_class_removes_its_subject_teachers_there_only(): void
    {
        $otherClass = SchoolClass::factory()->for($this->class->academicYear)->create();
        $otherClass->subjects()->attach($this->maths);
        $otherSection = Section::factory()->for($otherClass, 'schoolClass')->create();
        $this->assign($this->section, $this->maths, $this->teacher)->assertOk();
        $this->assign($otherSection, $this->maths, $this->teacher)->assertOk();

        $this->withToken($this->adminToken)->putJson("/api/v1/classes/{$this->class->id}/subjects", ['subject_ids' => []])->assertOk();

        $this->assertSame([$otherSection->id], SectionSubjectTeacher::query()->pluck('section_id')->all());
    }

    public function test_a_subject_teacher_sees_the_students_of_their_sections_and_no_others(): void
    {
        $taught = StudentEnrollment::factory()->create(['section_id' => $this->section->id])->student;
        StudentEnrollment::factory()->create(['section_id' => Section::factory()->for($this->class, 'schoolClass')->create()->id]);
        $this->assign($this->section, $this->maths, $this->teacher)->assertOk();

        $this->withToken($this->issueToken($this->teacher->email))->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $taught->id);
    }

    public function test_a_teacher_lists_the_sections_and_subjects_they_teach_this_year(): void
    {
        $lastYearSection = Section::factory()->for(SchoolClass::factory()->create(), 'schoolClass')->create();
        $lastYearSection->schoolClass->subjects()->attach($this->maths);
        $this->assign($this->section, $this->maths, $this->teacher)->assertOk();
        $this->assign($lastYearSection, $this->maths, $this->teacher)->assertOk();

        $this->withToken($this->issueToken($this->teacher->email))->getJson('/api/v1/me/teaching')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.section.name', 'A')
            ->assertJsonPath('data.0.section.class.name', 'Class 1')
            ->assertJsonPath('data.0.subject.name', 'Mathematics');
    }

    public function test_subjects_are_listed_with_the_teachers_linked_to_them(): void
    {
        $this->withToken($this->adminToken)->getJson('/api/v1/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Mathematics')
            ->assertJsonPath('data.0.teachers.0.name', 'Satheesh');
    }

    private function assign(Section $section, Subject $subject, ?User $teacher): TestResponse
    {
        return $this->withToken($this->adminToken)->putJson(
            "/api/v1/sections/{$section->id}/subjects/{$subject->id}/teacher",
            ['teacher_id' => $teacher?->id],
        );
    }
}
