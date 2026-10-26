<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Examination\Models\Exam;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * A request works in the year named by its X-Academic-Year header, or the
 * current year without one: lists, placements, own timetables and teacher
 * visibility all follow it.
 */
class SelectedAcademicYearTest extends TestCase
{
    private AcademicYear $lastYear;

    private AcademicYear $currentYear;

    private User $teacher;

    private Section $taughtLastYear;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastYear = AcademicYear::factory()->create(['name' => '2025-26']);
        $this->currentYear = AcademicYear::factory()->current()->create(['name' => '2026-27']);
        $this->teacher = $this->createUser(Role::Teacher);

        // The teacher was class teacher of Class 1 A last year and teaches nothing this year.
        $this->taughtLastYear = $this->section($this->lastYear, 'Class 1', $this->teacher);
        $this->student = StudentEnrollment::factory()->create(['section_id' => $this->taughtLastYear->id])->student;
        StudentEnrollment::factory()->for($this->student)->create(['section_id' => $this->section($this->currentYear, 'Class 2')->id]);
    }

    public function test_the_student_list_shows_placements_in_the_selected_year(): void
    {
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $this->withToken($token)->getJson('/api/v1/students')
            ->assertJsonPath('data.0.enrollments.0.class.name', 'Class 2');

        $this->withToken($token)->withHeader('X-Academic-Year', (string) $this->lastYear->id)->getJson('/api/v1/students')
            ->assertJsonPath('data.0.enrollments.0.class.name', 'Class 1');
    }

    public function test_a_teacher_sees_the_students_of_the_sections_they_taught_in_the_selected_year(): void
    {
        $token = $this->issueToken($this->teacher->email);

        $this->withToken($token)->getJson('/api/v1/students')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson("/api/v1/students/{$this->student->id}")->assertForbidden();

        $lastYear = ['X-Academic-Year' => (string) $this->lastYear->id];
        $this->withToken($token)->withHeaders($lastYear)->getJson('/api/v1/students')->assertJsonPath('data.0.id', $this->student->id);
        $this->withToken($token)->withHeaders($lastYear)->getJson("/api/v1/students/{$this->student->id}")->assertOk();
    }

    public function test_a_teachers_own_timetable_and_sections_follow_the_selected_year(): void
    {
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        SectionSubjectTeacher::factory()->for($this->taughtLastYear)->for($subject)->create(['teacher_id' => $this->teacher->id]);
        TimetableEntry::factory()->for($this->taughtLastYear)->for($subject)->create(['teacher_id' => $this->teacher->id]);
        $token = $this->issueToken($this->teacher->email);

        $this->withToken($token)->getJson('/api/v1/me/timetable')->assertJsonCount(0, 'data.entries');
        $this->withToken($token)->getJson('/api/v1/me/teaching')->assertJsonCount(0, 'data');

        $lastYear = ['X-Academic-Year' => (string) $this->lastYear->id];
        $this->withToken($token)->withHeaders($lastYear)->getJson('/api/v1/me/timetable')->assertJsonPath('data.entries.0.subject.name', 'Mathematics');
        $this->withToken($token)->withHeaders($lastYear)->getJson('/api/v1/me/teaching')->assertJsonPath('data.0.subject.name', 'Mathematics');
    }

    public function test_admissions_list_the_applications_of_the_selected_year(): void
    {
        $nextYear = AcademicYear::factory()->create(['name' => '2027-28']);
        AdmissionApplication::factory()->for(SchoolClass::factory()->for($nextYear), 'schoolClass')->create(['student_name' => 'Early Applicant']);
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $this->withToken($token)->getJson('/api/v1/admissions')->assertJsonCount(0, 'data');
        $this->withToken($token)->withHeader('X-Academic-Year', (string) $nextYear->id)->getJson('/api/v1/admissions')
            ->assertJsonPath('data.0.student_name', 'Early Applicant');
    }

    public function test_a_marks_sheet_shows_a_teachers_students_in_the_exams_year_whatever_year_is_selected(): void
    {
        $subject = Subject::factory()->create();
        SectionSubjectTeacher::factory()->for($this->taughtLastYear)->for($subject)->create(['teacher_id' => $this->teacher->id]);
        $exam = Exam::factory()->for($this->lastYear)->create();

        $this->withToken($this->issueToken($this->teacher->email))
            ->getJson("/api/v1/exams/{$exam->id}/marks?section_id={$this->taughtLastYear->id}&subject_id={$subject->id}")
            ->assertOk()
            ->assertJsonPath('data.students.0.student.id', $this->student->id);
    }

    public function test_making_another_year_current_moves_requests_without_a_header_at_once(): void
    {
        $teacherToken = $this->issueToken($this->teacher->email);
        $this->withToken($teacherToken)->getJson('/api/v1/students')->assertJsonCount(0, 'data');

        $this->withToken($this->issueToken($this->createUser(Role::Admin)->email))
            ->postJson("/api/v1/academic-years/{$this->lastYear->id}/make-current")
            ->assertOk();

        $this->withToken($teacherToken)->getJson('/api/v1/students')->assertJsonPath('data.0.id', $this->student->id);
    }

    #[TestWith(['999999'])]
    #[TestWith(['last-year'])]
    public function test_a_header_naming_no_year_is_rejected(string $header): void
    {
        $this->withToken($this->issueToken($this->createUser(Role::Admin)->email))
            ->withHeader('X-Academic-Year', $header)
            ->getJson('/api/v1/students')
            ->assertJsonValidationErrors(['academic_year' => 'The selected academic year does not exist.']);
    }

    private function section(AcademicYear $year, string $className, ?User $classTeacher = null): Section
    {
        return Section::factory()
            ->for(SchoolClass::factory()->for($year)->create(['name' => $className]), 'schoolClass')
            ->create(['class_teacher_id' => $classTeacher?->id]);
    }
}
