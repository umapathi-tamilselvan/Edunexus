<?php

namespace Tests\Feature\Examination;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Examination\Models\ExamPaper;
use App\Domain\Identity\Enums\Role;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Tests\TestCase;

/**
 * A student's score out of 5 per subject: every exam's marks as a
 * percentage of that paper's maximum, averaged, scaled to 5.
 */
class SubjectScoreTest extends TestCase
{
    private string $adminToken;

    private AcademicYear $year;

    private Subject $maths;

    private Student $student;

    private StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->year = AcademicYear::factory()->current()->create(['name' => '2026-27']);
        $this->maths = Subject::factory()->create(['name' => 'Mathematics']);
        $this->student = Student::factory()->create();
        $this->enrollment = $this->enrollIn($this->year);
    }

    public function test_a_score_averages_each_exams_percentage_of_its_papers_maximum_and_leaves_out_absences(): void
    {
        $quarterly = $this->exam('Quarterly', '2026-09-14');
        ExamPaper::factory()->for($quarterly)->create(['class_id' => $this->enrollment->section->class_id, 'subject_id' => $this->maths->id, 'max_marks' => 50]);
        $this->mark($quarterly, 40);
        $this->mark($this->exam('Half yearly', '2026-12-07'), 90);
        ExamMark::factory()->absent()->for($this->exam('Unit test', '2026-08-03'))->for($this->enrollment, 'enrollment')->for($this->maths)->create();

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$this->student->id}/scores")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.name', 'Mathematics')
            ->assertJsonPath('data.0.score', 4.3)
            ->assertJsonPath('data.0.average_percentage', 85)
            ->assertJsonCount(2, 'data.0.exams')
            ->assertJsonPath('data.0.exams.0.exam', 'Quarterly')
            ->assertJsonPath('data.0.exams.0.max_marks', 50)
            ->assertJsonPath('data.0.exams.0.percentage', 80)
            ->assertJsonPath('data.0.exams.1.percentage', 90);
    }

    public function test_a_score_counts_exams_from_every_academic_year_and_subjects_are_listed_by_name(): void
    {
        $lastYear = AcademicYear::factory()->create(['name' => '2025-26', 'starts_on' => '2025-06-01', 'ends_on' => '2026-04-30']);
        $lastEnrollment = $this->enrollIn($lastYear);
        $english = Subject::factory()->create(['name' => 'English']);
        ExamMark::factory()->for(Exam::factory()->for($lastYear)->create(['name' => 'Annual', 'max_marks' => 100]))->for($lastEnrollment, 'enrollment')->for($this->maths)->create(['marks' => 60]);
        $this->mark($this->exam('Quarterly', '2026-09-14'), 80);
        ExamMark::factory()->for($this->exam('Half yearly', '2026-12-07'))->for($this->enrollment, 'enrollment')->for($english)->create(['marks' => 100]);

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$this->student->id}/scores")
            ->assertJsonPath('data.0.subject.name', 'English')
            ->assertJsonPath('data.0.score', 5)
            ->assertJsonPath('data.1.subject.name', 'Mathematics')
            ->assertJsonPath('data.1.score', 3.5)
            ->assertJsonPath('data.1.exams.0.academic_year', '2025-26')
            ->assertJsonPath('data.1.exams.1.academic_year', '2026-27');
    }

    public function test_a_teacher_gets_403_for_a_student_outside_their_sections(): void
    {
        $this->withToken($this->issueToken($this->createUser(Role::Teacher)->email))
            ->getJson("/api/v1/students/{$this->student->id}/scores")
            ->assertForbidden();
    }

    public function test_a_role_without_exam_view_gets_403(): void
    {
        $this->withToken($this->issueToken($this->createUser(Role::Accountant)->email))
            ->getJson("/api/v1/students/{$this->student->id}/scores")
            ->assertForbidden();
    }

    private function enrollIn(AcademicYear $year): StudentEnrollment
    {
        $class = SchoolClass::factory()->for($year)->create();
        $class->subjects()->attach($this->maths);

        return StudentEnrollment::factory()->for($this->student)->create(['section_id' => Section::factory()->for($class, 'schoolClass')->create()->id]);
    }

    private function exam(string $name, string $startsOn): Exam
    {
        return Exam::factory()->for($this->year)->create(['name' => $name, 'max_marks' => 100, 'starts_on' => $startsOn]);
    }

    private function mark(Exam $exam, float $marks): void
    {
        ExamMark::factory()->for($exam)->for($this->enrollment, 'enrollment')->for($this->maths)->create(['marks' => $marks]);
    }
}
