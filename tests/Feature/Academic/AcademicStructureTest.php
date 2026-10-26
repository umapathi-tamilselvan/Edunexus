<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\StudentEnrollment;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
    }

    public function test_the_first_academic_year_becomes_current_and_later_ones_do_not(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/academic-years', ['name' => '2026-27', 'starts_on' => '2026-06-01', 'ends_on' => '2027-04-30'])
            ->assertCreated()
            ->assertJsonPath('data.is_current', true);

        $this->withToken($this->adminToken)->postJson('/api/v1/academic-years', ['name' => '2027-28', 'starts_on' => '2027-06-01', 'ends_on' => '2028-04-30'])
            ->assertCreated()
            ->assertJsonPath('data.is_current', false);
    }

    public function test_making_a_year_current_leaves_exactly_one_current_year(): void
    {
        $old = AcademicYear::factory()->current()->create();
        $next = AcademicYear::factory()->create();

        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$next->id}/make-current")
            ->assertOk()
            ->assertJsonPath('data.is_current', true);

        $this->assertFalse($old->fresh()->is_current);
        $this->assertSame([$next->id], AcademicYear::query()->current()->pluck('id')->all());
    }

    public function test_an_academic_year_must_end_after_it_starts_and_have_a_unique_name(): void
    {
        AcademicYear::factory()->create(['name' => '2026-27']);

        $this->withToken($this->adminToken)->postJson('/api/v1/academic-years', ['name' => '2026-27', 'starts_on' => '2026-06-01', 'ends_on' => '2026-05-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name has already been taken.',
                'ends_on' => 'The ends on field must be a date after starts on.',
            ]);
    }

    public function test_classes_sections_and_subjects_build_the_year_structure(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $teacher = $this->createUser(Role::Teacher, ['name' => 'Meena']);
        $maths = Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathematics']);

        $classId = $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$year->id}/classes", ['name' => 'Class 5', 'sort_order' => 5])
            ->assertCreated()->json('data.id');

        $this->withToken($this->adminToken)->postJson("/api/v1/classes/{$classId}/sections", ['name' => 'A', 'capacity' => 30, 'class_teacher_id' => $teacher->id])
            ->assertCreated()
            ->assertJsonPath('data.class_teacher.name', 'Meena');

        $this->withToken($this->adminToken)->putJson("/api/v1/classes/{$classId}/subjects", ['subject_ids' => [$maths->id]])->assertOk();

        $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$year->id}")
            ->assertOk()
            ->assertJsonPath('data.classes.0.name', 'Class 5')
            ->assertJsonPath('data.classes.0.sections.0.name', 'A')
            ->assertJsonPath('data.classes.0.sections.0.capacity', 30)
            ->assertJsonPath('data.classes.0.sections.0.enrolled_count', 0)
            ->assertJsonPath('data.classes.0.subjects.0.code', 'MATH');
    }

    public function test_a_years_classes_are_listed_with_their_sections_and_subjects(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $class = SchoolClass::factory()->for($year)->create(['name' => 'Class 5']);
        Section::factory()->for($class, 'schoolClass')->create(['name' => 'A']);
        $class->subjects()->attach(Subject::factory()->create(['name' => 'Mathematics']));

        $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$year->id}/classes")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Class 5')
            ->assertJsonPath('data.0.sections.0.name', 'A')
            ->assertJsonPath('data.0.subjects.0.name', 'Mathematics');
    }

    public function test_a_class_is_renamed_and_reordered_keeping_names_unique_within_its_year(): void
    {
        $class = SchoolClass::factory()->create(['name' => 'Class 5', 'sort_order' => 5]);
        SchoolClass::factory()->for($class->academicYear)->create(['name' => 'Class 6']);

        $this->withToken($this->adminToken)->patchJson("/api/v1/classes/{$class->id}", ['name' => 'Class 6'])
            ->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->withToken($this->adminToken)->patchJson("/api/v1/classes/{$class->id}", ['name' => 'Class V', 'sort_order' => 15])
            ->assertOk()
            ->assertJsonPath('data.name', 'Class V')
            ->assertJsonPath('data.sort_order', 15);
    }

    public function test_a_section_shows_its_class_year_class_teacher_and_the_classs_subjects(): void
    {
        $year = AcademicYear::factory()->current()->create(['name' => '2026-27']);
        $class = SchoolClass::factory()->for($year)->create(['name' => 'Class 5']);
        $class->subjects()->attach(Subject::factory()->create(['name' => 'Mathematics']));
        $section = Section::factory()->for($class, 'schoolClass')->create(['name' => 'A', 'class_teacher_id' => $this->createUser(Role::Teacher, ['name' => 'Meena'])->id]);
        StudentEnrollment::factory()->create(['section_id' => $section->id]);

        $this->withToken($this->adminToken)->getJson("/api/v1/sections/{$section->id}")
            ->assertOk()
            ->assertJsonPath('data.enrolled_count', 1)
            ->assertJsonPath('data.class_teacher.name', 'Meena')
            ->assertJsonPath('data.class.name', 'Class 5')
            ->assertJsonPath('data.class.academic_year.name', '2026-27')
            ->assertJsonPath('data.class.academic_year.is_current', true)
            ->assertJsonPath('data.class.subjects.0.name', 'Mathematics');
    }

    public function test_a_subject_is_renamed_keeping_its_code_unique(): void
    {
        Subject::factory()->create(['code' => 'ENG']);
        $subject = Subject::factory()->create(['code' => 'MATH', 'name' => 'Maths']);

        $this->withToken($this->adminToken)->patchJson("/api/v1/subjects/{$subject->id}", ['code' => 'eng', 'name' => 'Maths'])
            ->assertJsonValidationErrors(['code' => 'The code has already been taken.']);
        $this->withToken($this->adminToken)->patchJson("/api/v1/subjects/{$subject->id}", ['code' => 'MATH', 'name' => 'Mathematics'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mathematics');
    }

    public function test_class_and_section_names_are_unique_only_within_their_parent(): void
    {
        $class = SchoolClass::factory()->create(['name' => 'Class 5']);
        Section::factory()->for($class, 'schoolClass')->create(['name' => 'A']);
        $nextYear = AcademicYear::factory()->create();

        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$class->academic_year_id}/classes", ['name' => 'Class 5'])
            ->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$nextYear->id}/classes", ['name' => 'Class 5'])
            ->assertCreated();

        $this->withToken($this->adminToken)->postJson("/api/v1/classes/{$class->id}/sections", ['name' => 'A'])
            ->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
    }

    public function test_a_subject_code_is_upper_cased_and_unique(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/subjects', ['code' => 'eng', 'name' => 'English'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ENG');

        $this->withToken($this->adminToken)->postJson('/api/v1/subjects', ['code' => 'ENG', 'name' => 'English 2'])
            ->assertJsonValidationErrors(['code' => 'The code has already been taken.']);
    }

    public function test_a_class_with_sections_and_a_section_with_students_cannot_be_deleted(): void
    {
        $enrollment = StudentEnrollment::factory()->create();
        $section = $enrollment->section;

        $this->withToken($this->adminToken)->deleteJson("/api/v1/classes/{$section->class_id}")
            ->assertJsonValidationErrors(['class' => 'Remove this class\'s sections first.']);
        $this->withToken($this->adminToken)->deleteJson("/api/v1/sections/{$section->id}")
            ->assertJsonValidationErrors(['section' => 'Students are enrolled in this section. Move them before deleting it.']);

        $empty = Section::factory()->create();
        $this->withToken($this->adminToken)->deleteJson("/api/v1/sections/{$empty->id}")->assertNoContent();
        $this->withToken($this->adminToken)->deleteJson("/api/v1/classes/{$empty->class_id}")->assertNoContent();
    }

    public function test_section_capacity_cannot_drop_below_its_enrolled_students(): void
    {
        $section = Section::factory()->create(['capacity' => 40]);
        StudentEnrollment::factory()->count(2)->sequence(['roll_no' => 1], ['roll_no' => 2])->create(['section_id' => $section->id]);

        $this->withToken($this->adminToken)->patchJson("/api/v1/sections/{$section->id}", ['name' => $section->name, 'capacity' => 1])
            ->assertJsonValidationErrors(['capacity' => 'The capacity cannot be lower than the number of students already enrolled.']);
    }

    public function test_teachers_can_view_but_not_change_the_structure(): void
    {
        $year = AcademicYear::factory()->create();
        $teacherToken = $this->issueToken($this->createUser(Role::Teacher)->email);

        $this->withToken($teacherToken)->getJson('/api/v1/academic-years')->assertOk();
        $this->withToken($teacherToken)->postJson("/api/v1/academic-years/{$year->id}/classes", ['name' => 'Class 1'])->assertForbidden();
    }

    public function test_guardians_and_students_cannot_see_the_structure(): void
    {
        $token = $this->issueToken($this->createUser(Role::Guardian)->email);

        $this->withToken($token)->getJson('/api/v1/academic-years')->assertForbidden();
    }

    public function test_the_structure_is_unavailable_when_the_academic_module_is_disabled(): void
    {
        config(['edunexus.modules.disable' => ['academic']]);

        $this->withToken($this->adminToken)->getJson('/api/v1/academic-years')
            ->assertForbidden()
            ->assertJsonPath('message', 'This module is not enabled for your institution.');
    }
}
