<?php

namespace Tests\Feature\Examination;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\StudentEnrollment;
use Tests\TestCase;

class ExamTest extends TestCase
{
    private string $adminToken;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->year = AcademicYear::factory()->current()->create();
    }

    public function test_an_exam_manager_creates_lists_shows_and_changes_an_exam(): void
    {
        $examId = $this->withToken($this->adminToken)
            ->postJson("/api/v1/academic-years/{$this->year->id}/exams", ['name' => 'Quarterly', 'max_marks' => 100, 'starts_on' => '2026-09-14', 'ends_on' => '2026-09-25'])
            ->assertCreated()
            ->assertJsonPath('data.academic_year.id', $this->year->id)
            ->json('data.id');

        $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$this->year->id}/exams")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Quarterly')
            ->assertJsonPath('data.0.marks_count', 0);

        $this->withToken($this->adminToken)->patchJson("/api/v1/exams/{$examId}", ['name' => 'Quarterly exam', 'max_marks' => 50])
            ->assertOk()
            ->assertJsonPath('data.max_marks', 50);

        $this->withToken($this->adminToken)->getJson("/api/v1/exams/{$examId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Quarterly exam')
            ->assertJsonPath('data.starts_on', '2026-09-14');
    }

    public function test_an_exam_name_is_unique_within_its_year_and_ends_on_or_after_it_starts(): void
    {
        Exam::factory()->for($this->year)->create(['name' => 'Quarterly']);
        $otherYear = AcademicYear::factory()->create();

        $this->withToken($this->adminToken)
            ->postJson("/api/v1/academic-years/{$this->year->id}/exams", ['name' => 'Quarterly', 'max_marks' => 100, 'starts_on' => '2026-09-14', 'ends_on' => '2026-09-01'])
            ->assertJsonValidationErrors([
                'name' => 'The name has already been taken.',
                'ends_on' => 'The ends on field must be a date after or equal to starts on.',
            ]);

        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$otherYear->id}/exams", ['name' => 'Quarterly', 'max_marks' => 100])
            ->assertCreated();
    }

    public function test_an_exam_with_marks_cannot_be_deleted_but_one_without_can(): void
    {
        $marked = ExamMark::factory()->create()->exam;
        $unmarked = Exam::factory()->for($this->year)->create();

        $this->withToken($this->adminToken)->deleteJson("/api/v1/exams/{$marked->id}")
            ->assertJsonValidationErrors(['exam' => 'Marks have been entered for this exam, so it cannot be deleted.']);
        $this->withToken($this->adminToken)->deleteJson("/api/v1/exams/{$unmarked->id}")->assertNoContent();

        $this->assertModelExists($marked);
        $this->assertModelMissing($unmarked);
    }

    public function test_the_exam_maximum_cannot_drop_below_marks_entered_under_it(): void
    {
        [$exam, $class, $subject] = $this->examWithMark(marks: 80);

        $this->withToken($this->adminToken)->patchJson("/api/v1/exams/{$exam->id}", ['name' => $exam->name, 'max_marks' => 75])
            ->assertJsonValidationErrors(['max_marks' => 'A student already has 80 marks, so the maximum cannot be lower.']);

        // Marks under a paper's own maximum don't hold the exam's maximum up.
        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$exam->id}/classes/{$class->id}/subjects/{$subject->id}/max-marks", ['max_marks' => 90])->assertOk();
        $this->withToken($this->adminToken)->patchJson("/api/v1/exams/{$exam->id}", ['name' => $exam->name, 'max_marks' => 75])->assertOk();
    }

    public function test_a_paper_maximum_applies_to_one_class_subject_and_resetting_it_removes_the_paper(): void
    {
        $exam = Exam::factory()->for($this->year)->create(['max_marks' => 100]);
        $class = SchoolClass::factory()->for($this->year)->create();
        $subject = Subject::factory()->create();
        $class->subjects()->attach($subject);
        $url = "/api/v1/exams/{$exam->id}/classes/{$class->id}/subjects/{$subject->id}/max-marks";

        $this->withToken($this->adminToken)->putJson($url, ['max_marks' => 50])
            ->assertOk()
            ->assertJsonPath('data.max_marks', 50);
        $this->assertDatabaseHas('exam_papers', ['exam_id' => $exam->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'max_marks' => 50]);

        $this->withToken($this->adminToken)->putJson($url, ['max_marks' => 100])->assertOk();
        $this->assertDatabaseMissing('exam_papers', ['exam_id' => $exam->id]);
    }

    public function test_a_paper_maximum_cannot_drop_below_marks_entered_under_it(): void
    {
        [$exam, $class, $subject] = $this->examWithMark(marks: 42.5);

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$exam->id}/classes/{$class->id}/subjects/{$subject->id}/max-marks", ['max_marks' => 40])
            ->assertJsonValidationErrors(['max_marks' => 'A student already has 42.5 marks, so the maximum cannot be lower.']);
    }

    public function test_a_paper_maximum_needs_a_class_of_the_exams_year_that_studies_the_subject(): void
    {
        $exam = Exam::factory()->for($this->year)->create(['name' => 'Quarterly']);
        $otherYearClass = SchoolClass::factory()->create(['name' => 'Class 9']);
        $class = SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']);
        $art = Subject::factory()->create(['name' => 'Art']);

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$exam->id}/classes/{$otherYearClass->id}/subjects/{$art->id}/max-marks", ['max_marks' => 50])
            ->assertJsonValidationErrors(['class_id' => "Class 9 is not in Quarterly's academic year."]);
        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$exam->id}/classes/{$class->id}/subjects/{$art->id}/max-marks", ['max_marks' => 50])
            ->assertJsonValidationErrors(['subject_id' => 'Art is not offered to Class 5.']);
    }

    public function test_teachers_can_view_exams_but_not_change_them(): void
    {
        $exam = Exam::factory()->for($this->year)->create();
        $teacherToken = $this->issueToken($this->createUser(Role::Teacher)->email);

        $this->withToken($teacherToken)->getJson("/api/v1/exams/{$exam->id}")->assertOk();
        $this->withToken($teacherToken)->postJson("/api/v1/academic-years/{$this->year->id}/exams", ['name' => 'Annual', 'max_marks' => 100])->assertForbidden();
        $this->withToken($teacherToken)->deleteJson("/api/v1/exams/{$exam->id}")->assertForbidden();
    }

    public function test_exams_are_unavailable_when_the_examination_module_is_disabled(): void
    {
        config(['edunexus.modules.disable' => ['examination']]);

        $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$this->year->id}/exams")->assertForbidden();
    }

    /**
     * An exam of the year with one student's marks in one class subject.
     *
     * @return array{Exam, SchoolClass, Subject}
     */
    private function examWithMark(float $marks): array
    {
        $exam = Exam::factory()->for($this->year)->create(['max_marks' => 100]);
        $class = SchoolClass::factory()->for($this->year)->create();
        $subject = Subject::factory()->create();
        $class->subjects()->attach($subject);
        $enrollment = StudentEnrollment::factory()->create(['section_id' => Section::factory()->for($class, 'schoolClass')->create()->id]);

        ExamMark::factory()->for($exam)->for($enrollment, 'enrollment')->for($subject)->create(['marks' => $marks]);

        return [$exam, $class, $subject];
    }
}
