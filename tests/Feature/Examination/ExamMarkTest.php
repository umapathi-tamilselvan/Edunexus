<?php

namespace Tests\Feature\Examination;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Marks sheets: one section's marks in one subject of an exam.
 */
class ExamMarkTest extends TestCase
{
    private User $admin;

    private string $adminToken;

    private AcademicYear $year;

    private Section $section;

    private Subject $maths;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser(Role::Admin);
        $this->adminToken = $this->issueToken($this->admin->email);
        $this->year = AcademicYear::factory()->current()->create();
        $class = SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']);
        $this->section = Section::factory()->for($class, 'schoolClass')->create(['name' => 'A']);
        $this->maths = Subject::factory()->create(['name' => 'Mathematics']);
        $class->subjects()->attach($this->maths);
        $this->exam = Exam::factory()->for($this->year)->create(['name' => 'Quarterly', 'max_marks' => 100]);
    }

    public function test_the_sheet_lists_the_sections_students_in_roll_order_with_their_marks_and_maximum(): void
    {
        $bala = $this->enroll('Bala', rollNo: 2);
        $this->enroll('Anu', rollNo: 1);
        ExamMark::factory()->for($this->exam)->for($bala, 'enrollment')->for($this->maths)->create(['marks' => 67.5]);

        $this->withToken($this->adminToken)->getJson($this->sheetUrl())
            ->assertOk()
            ->assertJsonPath('data.max_marks', 100)
            ->assertJsonPath('data.can_enter_marks', true)
            ->assertJsonPath('data.can_set_max_marks', true)
            ->assertJsonPath('data.students.0.student.name', 'Anu')
            ->assertJsonPath('data.students.0.marks', null)
            ->assertJsonPath('data.students.1.student.name', 'Bala')
            ->assertJsonPath('data.students.1.marks', 67.5)
            ->assertJsonPath('data.students.1.is_absent', false);
    }

    public function test_saving_a_sheet_records_marks_and_absences_and_a_blank_clears_the_entry(): void
    {
        $present = $this->enroll('Anu', rollNo: 1);
        $absent = $this->enroll('Bala', rollNo: 2);
        $cleared = $this->enroll('Chitra', rollNo: 3);
        $oldMark = ExamMark::factory()->for($this->exam)->for($cleared, 'enrollment')->for($this->maths)->create(['marks' => 30]);
        $auditsBefore = (int) Audit::query()->max('id');

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([
            ['enrollment_id' => $present->id, 'marks' => 45.5],
            ['enrollment_id' => $absent->id, 'marks' => null, 'is_absent' => true],
            ['enrollment_id' => $cleared->id, 'marks' => null],
        ]))->assertNoContent();

        $this->assertDatabaseHas('exam_marks', ['enrollment_id' => $present->id, 'marks' => 45.5, 'is_absent' => false, 'recorded_by' => $this->admin->id]);
        $this->assertDatabaseHas('exam_marks', ['enrollment_id' => $absent->id, 'marks' => null, 'is_absent' => true]);
        $this->assertModelMissing($oldMark);

        $audits = Audit::query()->where('id', '>', $auditsBefore)->where('auditable_type', ExamMark::class)->orderBy('id')->get();
        $this->assertSame(['created', 'created', 'deleted'], $audits->pluck('event')->all());
        $this->assertSame($this->admin->id, $audits->last()->user_id);
        $this->assertSame('30.00', $audits->last()->old_values['marks']);
    }

    public function test_marks_above_the_papers_maximum_are_rejected_and_nothing_is_saved(): void
    {
        ExamPaper::factory()->for($this->exam)->create(['class_id' => $this->section->class_id, 'subject_id' => $this->maths->id, 'max_marks' => 50]);
        $student = $this->enroll('Anu', rollNo: 1);

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([['enrollment_id' => $student->id, 'marks' => 51]]))
            ->assertJsonValidationErrors(['entries.0.marks' => 'Marks cannot be more than 50.']);

        $this->assertDatabaseCount('exam_marks', 0);
    }

    public function test_marks_must_be_a_number_from_zero_with_at_most_two_decimals(): void
    {
        $students = [$this->enroll('Anu', rollNo: 1), $this->enroll('Bala', rollNo: 2), $this->enroll('Chitra', rollNo: 3)];

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([
            ['enrollment_id' => $students[0]->id, 'marks' => 'forty'],
            ['enrollment_id' => $students[1]->id, 'marks' => -1],
            ['enrollment_id' => $students[2]->id, 'marks' => 40.125],
        ]))->assertJsonValidationErrors([
            'entries.0.marks' => 'Enter marks as a number.',
            'entries.1.marks' => 'Marks cannot be negative.',
            'entries.2.marks' => 'Use at most two decimal places.',
        ]);
    }

    public function test_a_student_of_another_section_is_rejected(): void
    {
        $outsider = StudentEnrollment::factory()->create(['section_id' => Section::factory()->for($this->section->schoolClass, 'schoolClass')->create()->id]);

        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([['enrollment_id' => $outsider->id, 'marks' => 40]]))
            ->assertJsonValidationErrors(['entries.0.enrollment_id' => 'This student is not in Class 5 A.']);
    }

    public function test_a_section_of_another_academic_year_is_rejected(): void
    {
        $lastYear = Section::factory()->for(SchoolClass::factory()->create(['name' => 'Class 4']), 'schoolClass')->create(['name' => 'B']);
        $message = "Class 4 B is not in Quarterly's academic year.";

        $this->withToken($this->adminToken)->getJson("/api/v1/exams/{$this->exam->id}/marks?section_id={$lastYear->id}&subject_id={$this->maths->id}")
            ->assertJsonValidationErrors(['section_id' => $message]);
        $this->withToken($this->adminToken)->putJson("/api/v1/exams/{$this->exam->id}/marks", ['section_id' => $lastYear->id, 'subject_id' => $this->maths->id, 'entries' => []])
            ->assertJsonValidationErrors(['section_id' => $message]);
    }

    public function test_a_subject_teacher_enters_marks_for_their_section_and_subject(): void
    {
        $teacher = $this->createUser(Role::Teacher, ['subject_id' => $this->maths->id]);
        SectionSubjectTeacher::factory()->for($this->section)->for($this->maths)->create(['teacher_id' => $teacher->id]);
        $student = $this->enroll('Anu', rollNo: 1);
        $token = $this->issueToken($teacher->email);

        $this->withToken($token)->getJson($this->sheetUrl())
            ->assertJsonPath('data.can_enter_marks', true)
            ->assertJsonPath('data.can_set_max_marks', false);
        $this->withToken($token)->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([['enrollment_id' => $student->id, 'marks' => 40]]))
            ->assertNoContent();

        $this->assertDatabaseHas('exam_marks', ['enrollment_id' => $student->id, 'marks' => 40, 'recorded_by' => $teacher->id]);
    }

    public function test_a_teacher_who_does_not_teach_the_subject_to_the_section_gets_403(): void
    {
        $teacher = $this->createUser(Role::Teacher, ['subject_id' => $this->maths->id]);
        $otherSection = Section::factory()->for($this->section->schoolClass, 'schoolClass')->create();
        SectionSubjectTeacher::factory()->for($otherSection)->for($this->maths)->create(['teacher_id' => $teacher->id]);
        $student = $this->enroll('Anu', rollNo: 1);

        $this->withToken($this->issueToken($teacher->email))
            ->putJson("/api/v1/exams/{$this->exam->id}/marks", $this->sheet([['enrollment_id' => $student->id, 'marks' => 40]]))
            ->assertForbidden();

        $this->assertDatabaseCount('exam_marks', 0);
    }

    private function enroll(string $name, int $rollNo): StudentEnrollment
    {
        return StudentEnrollment::factory()
            ->for(Student::factory()->state(['name' => $name]))
            ->create(['section_id' => $this->section->id, 'roll_no' => $rollNo]);
    }

    private function sheetUrl(): string
    {
        return "/api/v1/exams/{$this->exam->id}/marks?section_id={$this->section->id}&subject_id={$this->maths->id}";
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function sheet(array $entries): array
    {
        return ['section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'entries' => $entries];
    }
}
