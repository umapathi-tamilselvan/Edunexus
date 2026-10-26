<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

/**
 * Every list endpoint runs a fixed number of queries however many rows it
 * returns. Each test counts one request's queries with one row, adds two
 * more rows with all their relations, and expects the same count, within
 * the endpoint's budget. A count that grows with rows is an N+1.
 */
class ListQueryCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A token's last_used_at only changes when the clock moves, so a
        // frozen clock keeps that write out of the counts.
        $this->freezeTime();
    }

    public function test_academic_years_runs_a_fixed_number_of_queries(): void
    {
        AcademicYear::factory()->current()->create();

        $this->assertQueryBudget(5, $this->adminToken(), '/api/v1/academic-years', fn () => AcademicYear::factory()->count(2)->create());
    }

    public function test_an_academic_years_structure_runs_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $this->addClassWithSection($year);

        $this->assertQueryBudget(9, $this->adminToken(), "/api/v1/academic-years/{$year->id}", function () use ($year): void {
            $this->addClassWithSection($year);
            $this->addClassWithSection($year);
        });
    }

    public function test_a_years_classes_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $this->addClassWithSection($year);

        $this->assertQueryBudget(8, $this->adminToken(), "/api/v1/academic-years/{$year->id}/classes", function () use ($year): void {
            $this->addClassWithSection($year);
            $this->addClassWithSection($year);
        });
    }

    public function test_a_years_periods_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        Period::factory()->for($year)->create();

        $this->assertQueryBudget(6, $this->adminToken(), "/api/v1/academic-years/{$year->id}/periods", fn () => Period::factory()->count(2)->for($year)->create());
    }

    public function test_subjects_run_a_fixed_number_of_queries(): void
    {
        $this->addSubjectWithTeacher();

        $this->assertQueryBudget(6, $this->adminToken(), '/api/v1/subjects', function (): void {
            $this->addSubjectWithTeacher();
            $this->addSubjectWithTeacher();
        });
    }

    public function test_a_section_with_its_subjects_and_teachers_runs_a_fixed_number_of_queries(): void
    {
        $section = $this->sectionIn(AcademicYear::factory()->current()->create());
        $this->addSubjectTeacher($section);

        $this->assertQueryBudget(12, $this->adminToken(), "/api/v1/sections/{$section->id}", function () use ($section): void {
            $this->addSubjectTeacher($section);
            $this->addSubjectTeacher($section);
        });
    }

    public function test_a_sections_timetable_runs_a_fixed_number_of_queries(): void
    {
        $section = $this->sectionIn(AcademicYear::factory()->current()->create());
        $this->addTimetableSlot($section, $this->createUser());
        $this->addSubjectTeacher($section);

        $this->assertQueryBudget(13, $this->adminToken(), "/api/v1/sections/{$section->id}/timetable", function () use ($section): void {
            $this->addTimetableSlot($section, $this->createUser());
            $this->addTimetableSlot($section, $this->createUser());
            $this->addSubjectTeacher($section);
        });
    }

    public function test_a_teachers_own_timetable_runs_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $teacher = $this->createUser();
        $this->addTimetableSlot($this->sectionIn($year), $teacher);

        $this->assertQueryBudget(7, $this->issueToken($teacher->email), '/api/v1/me/timetable', function () use ($year, $teacher): void {
            $this->addTimetableSlot($this->sectionIn($year), $teacher);
            $this->addTimetableSlot($this->sectionIn($year), $teacher);
        });
    }

    public function test_a_teachers_own_sections_and_subjects_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $teacher = $this->createUser();
        $this->addSubjectTeacher($this->sectionIn($year), $teacher);

        $this->assertQueryBudget(6, $this->issueToken($teacher->email), '/api/v1/me/teaching', function () use ($year, $teacher): void {
            $this->addSubjectTeacher($this->sectionIn($year), $teacher);
            $this->addSubjectTeacher($this->sectionIn($year), $teacher);
        });
    }

    public function test_users_run_a_fixed_number_of_queries(): void
    {
        $token = $this->adminToken();
        $this->createUser(Role::Teacher, ['subject_id' => Subject::factory()->create()->id]);

        $this->assertQueryBudget(8, $token, '/api/v1/users', function (): void {
            $this->createUser(Role::Teacher, ['subject_id' => Subject::factory()->create()->id]);
            $this->createUser(Role::Accountant);
        });
    }

    public function test_roles_run_a_fixed_number_of_queries(): void
    {
        $token = $this->adminToken();

        $this->assertQueryBudget(6, $token, '/api/v1/roles', function (): void {
            RoleModel::create(['name' => 'Librarian', 'guard_name' => 'web'])->givePermissionTo(Permission::StudentView->value);
            RoleModel::create(['name' => 'Driver', 'guard_name' => 'web'])->givePermissionTo(Permission::AcademicView->value);
        });
    }

    public function test_students_for_a_user_who_sees_everyone_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $this->enrollIn($this->sectionIn($year));

        $this->assertQueryBudget(10, $this->adminToken(), '/api/v1/students', function () use ($year): void {
            $this->enrollIn($this->sectionIn($year));
            $this->enrollIn($this->sectionIn($year));
        });
    }

    public function test_students_for_a_teacher_who_sees_their_sections_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $teacher = $this->createUser();
        $this->enrollIn($this->sectionIn($year, $teacher));

        $this->assertQueryBudget(10, $this->issueToken($teacher->email), '/api/v1/students', function () use ($year, $teacher): void {
            $this->enrollIn($this->sectionIn($year, $teacher));
            $this->enrollIn($this->sectionIn($year, $teacher));
        });
    }

    public function test_a_students_guardians_and_enrollments_run_a_fixed_number_of_queries(): void
    {
        $student = Student::factory()->create();
        $this->addGuardianAndEnrollment($student);

        $this->assertQueryBudget(11, $this->adminToken(), "/api/v1/students/{$student->id}", function () use ($student): void {
            $this->addGuardianAndEnrollment($student);
            $this->addGuardianAndEnrollment($student);
        });
    }

    public function test_admissions_run_a_fixed_number_of_queries(): void
    {
        $class = SchoolClass::factory()->for(AcademicYear::factory()->current())->create();
        AdmissionApplication::factory()->for($class, 'schoolClass')->create();

        $this->assertQueryBudget(8, $this->adminToken(), '/api/v1/admissions', fn () => AdmissionApplication::factory()->count(2)->for($class, 'schoolClass')->create());
    }

    public function test_a_years_exams_run_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $this->addExamWithMark($year);

        $this->assertQueryBudget(6, $this->adminToken(), "/api/v1/academic-years/{$year->id}/exams", function () use ($year): void {
            $this->addExamWithMark($year);
            $this->addExamWithMark($year);
        });
    }

    public function test_a_marks_sheet_runs_a_fixed_number_of_queries(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $section = $this->sectionIn($year);
        $subject = Subject::factory()->create();
        $section->schoolClass->subjects()->attach($subject);
        $exam = Exam::factory()->for($year)->create();
        $this->addMark($exam, $this->enrollIn($section), $subject);

        $this->assertQueryBudget(12, $this->adminToken(), "/api/v1/exams/{$exam->id}/marks?section_id={$section->id}&subject_id={$subject->id}", function () use ($exam, $section, $subject): void {
            $this->addMark($exam, $this->enrollIn($section), $subject);
            $this->addMark($exam, $this->enrollIn($section), $subject);
        });
    }

    public function test_a_students_subject_scores_run_a_fixed_number_of_queries(): void
    {
        $enrollment = $this->enrollIn($this->sectionIn(AcademicYear::factory()->current()->create()));
        $this->addMarkInNewExam($enrollment);

        $this->assertQueryBudget(13, $this->adminToken(), "/api/v1/students/{$enrollment->student_id}/scores", function () use ($enrollment): void {
            $this->addMarkInNewExam($enrollment);
            $this->addMarkInNewExam($this->enrollIn($this->sectionIn(AcademicYear::factory()->create()), $enrollment->student));
        });
    }

    /**
     * Counts one request's queries before and after adding rows, and expects
     * the same count, at most the endpoint's budget. A warm-up request first
     * fills what every real request finds warm (permission map, token).
     */
    private function assertQueryBudget(int $budget, string $token, string $uri, callable $addRows): void
    {
        $before = $this->countQueries($token, $uri);

        $addRows();
        $after = $this->countQueries($token, $uri);

        $this->assertSame($before, $after, "GET {$uri} ran {$before} queries, then {$after} after rows were added: an N+1.");
        $this->assertLessThanOrEqual($budget, $after, "GET {$uri} ran {$after} queries; its budget is {$budget}.");
    }

    private function countQueries(string $token, string $uri): int
    {
        $this->withToken($token)->getJson($uri)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($token)->getJson($uri)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    private function adminToken(): string
    {
        return $this->issueToken($this->createUser(Role::Admin)->email);
    }

    private function sectionIn(AcademicYear $year, ?User $classTeacher = null): Section
    {
        return Section::factory()
            ->for(SchoolClass::factory()->for($year), 'schoolClass')
            ->create(['class_teacher_id' => $classTeacher?->id]);
    }

    private function enrollIn(Section $section, ?Student $student = null): StudentEnrollment
    {
        return StudentEnrollment::factory()
            ->for($student ?? Student::factory())
            ->create(['section_id' => $section->id]);
    }

    private function addClassWithSection(AcademicYear $year): void
    {
        $class = SchoolClass::factory()->for($year)->create();
        $class->subjects()->attach(Subject::factory()->create());

        $section = Section::factory()->for($class, 'schoolClass')->create(['class_teacher_id' => $this->createUser()->id]);
        $this->enrollIn($section);
    }

    private function addSubjectWithTeacher(): void
    {
        $this->createUser(Role::Teacher, ['subject_id' => Subject::factory()->create()->id]);
    }

    private function addSubjectTeacher(Section $section, ?User $teacher = null): void
    {
        $subject = Subject::factory()->create();
        $section->schoolClass->subjects()->attach($subject);
        $teacher ??= $this->createUser();
        $teacher->update(['subject_id' => $subject->id]);

        SectionSubjectTeacher::factory()->for($section)->for($subject)->create(['teacher_id' => $teacher->id]);
    }

    private function addTimetableSlot(Section $section, User $teacher): void
    {
        $subject = Subject::factory()->create();
        $section->schoolClass->subjects()->attach($subject);

        TimetableEntry::factory()->for($section)->for($subject)->create(['teacher_id' => $teacher->id]);
    }

    private function addGuardianAndEnrollment(Student $student): void
    {
        $student->guardians()->attach(Guardian::factory()->create(), ['relation' => 'mother', 'is_primary' => false]);

        $this->enrollIn($this->sectionIn(AcademicYear::factory()->create()), $student);
    }

    private function addExamWithMark(AcademicYear $year): void
    {
        $this->addMark(Exam::factory()->for($year)->create(), $this->enrollIn($this->sectionIn($year)), Subject::factory()->create());
    }

    private function addMark(Exam $exam, StudentEnrollment $enrollment, Subject $subject): void
    {
        ExamMark::factory()->for($exam)->for($enrollment, 'enrollment')->for($subject)->create();
    }

    private function addMarkInNewExam(StudentEnrollment $enrollment): void
    {
        $this->addMark(Exam::factory()->create(['academic_year_id' => $enrollment->academic_year_id]), $enrollment, Subject::factory()->create());
    }
}
