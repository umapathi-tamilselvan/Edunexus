<?php

namespace Tests\Feature\Attendance;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * Attendance sheets: one section, one school day. Also the student's
 * attendance summary.
 */
class AttendanceTest extends TestCase
{
    private User $admin;

    private string $adminToken;

    private AcademicYear $year;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser(Role::Admin);
        $this->adminToken = $this->issueToken($this->admin->email);
        $this->year = AcademicYear::factory()->current()->create([
            'starts_on' => today()->subMonths(3)->startOfMonth(),
            'ends_on' => today()->addMonths(3)->endOfMonth(),
        ]);
        $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']);
        $this->section = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'A']);
    }

    private function enroll(string $name, int $rollNo, ?Section $section = null): StudentEnrollment
    {
        return StudentEnrollment::factory()
            ->for(Student::factory()->state(['name' => $name]))
            ->create(['section_id' => ($section ?? $this->section)->id, 'roll_no' => $rollNo]);
    }

    private function sheetUrl(?Section $section = null): string
    {
        return '/api/v1/sections/'.($section ?? $this->section)->id.'/attendance';
    }

    /**
     * @param  list<array{enrollment_id: int, status: string|null, remark?: string|null}>  $entries
     */
    private function mark(string $token, string $date, array $entries, ?Section $section = null): TestResponse
    {
        return $this->withToken($token)->putJson($this->sheetUrl($section), ['date' => $date, 'entries' => $entries]);
    }

    private function daysAgo(int $days): string
    {
        return today()->subDays($days)->toDateString();
    }

    public function test_the_sheet_lists_students_in_roll_order_with_the_days_statuses_and_a_summary(): void
    {
        $bala = $this->enroll('Bala', rollNo: 2);
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->enroll('Chitra', rollNo: 3);

        $this->mark($this->adminToken, today()->toDateString(), [
            ['enrollment_id' => $bala->id, 'status' => 'present'],
            ['enrollment_id' => $anu->id, 'status' => 'absent', 'remark' => 'Fever'],
        ])->assertOk();

        $this->withToken($this->adminToken)->getJson($this->sheetUrl().'?date='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.can_mark', true)
            ->assertJsonPath('data.closed_reason', null)
            ->assertJsonPath('data.students.0.student.name', 'Anu')
            ->assertJsonPath('data.students.0.status', 'absent')
            ->assertJsonPath('data.students.0.remark', 'Fever')
            ->assertJsonPath('data.students.1.student.name', 'Bala')
            ->assertJsonPath('data.students.1.status', 'present')
            ->assertJsonPath('data.students.2.student.name', 'Chitra')
            ->assertJsonPath('data.students.2.status', null)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.late', 0)
            ->assertJsonPath('data.summary.unmarked', 1);
    }

    public function test_saving_a_sheet_reports_what_changed_and_a_blank_status_clears_the_record(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $chitra = $this->enroll('Chitra', rollNo: 2);
        $today = today()->toDateString();

        $this->mark($this->adminToken, $today, [
            ['enrollment_id' => $anu->id, 'status' => 'present'],
            ['enrollment_id' => $chitra->id, 'status' => 'late'],
        ])->assertOk()->assertJsonPath('data.marked', 2)->assertJsonPath('data.cleared', 0);

        $this->mark($this->adminToken, $today, [
            ['enrollment_id' => $anu->id, 'status' => null],
        ])->assertOk()->assertJsonPath('data.marked', 0)->assertJsonPath('data.cleared', 1);

        $this->assertSame(
            [$chitra->id],
            AttendanceRecord::query()->where('date', $today)->pluck('enrollment_id')->all(),
        );
    }

    public function test_saving_unchanged_values_writes_no_audit_rows(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $today = today()->toDateString();
        $entries = [['enrollment_id' => $anu->id, 'status' => 'present']];

        $this->mark($this->adminToken, $today, $entries)->assertOk();
        $auditsAfterFirstSave = Audit::query()->where('auditable_type', AttendanceRecord::class)->count();

        $this->mark($this->adminToken, $today, $entries)
            ->assertOk()
            ->assertJsonPath('data.marked', 0)
            ->assertJsonPath('data.cleared', 0);

        $this->assertSame($auditsAfterFirstSave, Audit::query()->where('auditable_type', AttendanceRecord::class)->count());
    }

    public function test_changing_a_recorded_status_is_audited_with_the_old_and_new_values(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $today = today()->toDateString();

        $this->mark($this->adminToken, $today, [['enrollment_id' => $anu->id, 'status' => 'absent']])->assertOk();
        $this->mark($this->adminToken, $today, [['enrollment_id' => $anu->id, 'status' => 'present']])->assertOk();

        $audit = Audit::query()->where('auditable_type', AttendanceRecord::class)->where('event', 'updated')->sole();

        $this->assertSame(['status' => 'absent', 'remark' => null], $audit->old_values);
        $this->assertSame(['status' => 'present', 'remark' => null], $audit->new_values);
        $this->assertSame($this->admin->id, $audit->user_id);
    }

    public function test_a_holiday_cannot_be_marked_and_the_sheet_says_why(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $yesterday = $this->daysAgo(1);
        $this->year->holidays()->create(['date' => $yesterday, 'name' => 'Diwali']);

        $this->withToken($this->adminToken)->getJson($this->sheetUrl().'?date='.$yesterday)
            ->assertOk()
            ->assertJsonPath('data.can_mark', false)
            ->assertJsonPath('data.closed_reason', now()->subDay()->format('j M Y').' is a holiday (Diwali).');

        $this->mark($this->adminToken, $yesterday, [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => now()->subDay()->format('j M Y').' is a holiday (Diwali).']);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_a_day_still_to_come_cannot_be_marked(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);

        $this->mark($this->adminToken, today()->addDay()->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Attendance cannot be taken for a day still to come.']);
    }

    public function test_a_day_outside_the_academic_year_cannot_be_marked(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $before = $this->year->starts_on->copy()->subDay();

        $this->mark($this->adminToken, $before->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => $before->format('j M Y').' is not in '.$this->year->name.'.']);
    }

    public function test_a_student_of_another_section_cannot_be_marked_on_this_sheet(): void
    {
        $otherSection = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'B']);
        $stranger = $this->enroll('Dev', rollNo: 1, section: $otherSection);

        $this->mark($this->adminToken, today()->toDateString(), [['enrollment_id' => $stranger->id, 'status' => 'present']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entries.0.enrollment_id' => 'This student is not in Class 5 A.']);
    }

    public function test_a_teacher_who_does_not_teach_the_section_cannot_mark_it(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $outsider = $this->createUser(Role::Teacher);
        $token = $this->issueToken($outsider->email);

        $this->withToken($token)->getJson($this->sheetUrl().'?date='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.can_mark', false)
            ->assertJsonPath('data.closed_reason', "Only this section's class teacher and subject teachers mark its attendance.");

        $this->mark($token, today()->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])->assertForbidden();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_the_class_teacher_marks_today_but_not_an_earlier_day(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $classTeacher = $this->createUser(Role::Teacher);
        $this->section->update(['class_teacher_id' => $classTeacher->id]);
        $token = $this->issueToken($classTeacher->email);

        $this->mark($token, today()->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])->assertOk();

        $this->mark($token, $this->daysAgo(1), [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertForbidden()
            ->assertJsonPath('message', 'Attendance can be changed only on the day. Ask an administrator to change an earlier day.');
    }

    public function test_a_subject_teacher_of_the_section_can_mark_it(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $maths = Subject::factory()->create(['name' => 'Mathematics']);
        $subjectTeacher = $this->createUser(Role::Teacher);
        $subjectTeacher->subjects()->attach($maths);
        SectionSubjectTeacher::factory()->create([
            'section_id' => $this->section->id,
            'subject_id' => $maths->id,
            'teacher_id' => $subjectTeacher->id,
        ]);

        $this->mark($this->issueToken($subjectTeacher->email), today()->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertOk()
            ->assertJsonPath('data.marked', 1);
    }

    public function test_a_principal_can_change_an_earlier_day(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $principal = $this->createUser(Role::Principal);

        $this->mark($this->issueToken($principal->email), $this->daysAgo(1), [['enrollment_id' => $anu->id, 'status' => 'present']])
            ->assertOk()
            ->assertJsonPath('data.marked', 1);
    }

    public function test_a_students_summary_gives_the_percentage_and_the_missed_days(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);

        $this->mark($this->adminToken, today()->toDateString(), [['enrollment_id' => $anu->id, 'status' => 'present']])->assertOk();
        $this->mark($this->adminToken, $this->daysAgo(1), [['enrollment_id' => $anu->id, 'status' => 'late']])->assertOk();
        $this->mark($this->adminToken, $this->daysAgo(2), [['enrollment_id' => $anu->id, 'status' => 'absent', 'remark' => 'Travel']])->assertOk();

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$anu->student_id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.section.name', 'A')
            ->assertJsonPath('data.days_marked', 3)
            ->assertJsonPath('data.days_attended', 2)
            ->assertJsonPath('data.percentage', 66.7)
            ->assertJsonPath('data.by_status.present', 1)
            ->assertJsonPath('data.by_status.late', 1)
            ->assertJsonPath('data.by_status.absent', 1)
            ->assertJsonPath('data.missed.0.status', 'absent')
            ->assertJsonPath('data.missed.0.remark', 'Travel');
    }

    public function test_a_day_on_leave_is_left_out_of_the_percentage(): void
    {
        $chitra = $this->enroll('Chitra', rollNo: 1);

        $this->mark($this->adminToken, today()->toDateString(), [['enrollment_id' => $chitra->id, 'status' => 'present']])->assertOk();
        $this->mark($this->adminToken, $this->daysAgo(1), [['enrollment_id' => $chitra->id, 'status' => 'leave']])->assertOk();
        $this->mark($this->adminToken, $this->daysAgo(2), [['enrollment_id' => $chitra->id, 'status' => 'absent']])->assertOk();

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$chitra->student_id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.days_marked', 2)
            ->assertJsonPath('data.days_attended', 1)
            ->assertJsonPath('data.percentage', 50)
            ->assertJsonPath('data.by_status.leave', 1);
    }

    public function test_a_student_with_only_leave_has_no_percentage(): void
    {
        $chitra = $this->enroll('Chitra', rollNo: 1);

        $this->mark($this->adminToken, today()->toDateString(), [['enrollment_id' => $chitra->id, 'status' => 'leave']])->assertOk();

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$chitra->student_id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.days_marked', 0)
            ->assertJsonPath('data.percentage', null);
    }

    public function test_a_student_without_an_enrollment_in_the_year_has_no_attendance_summary(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->adminToken)->getJson("/api/v1/students/{$student->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_an_unrelated_teacher_cannot_read_a_students_attendance(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $outsider = $this->createUser(Role::Teacher);

        $this->withToken($this->issueToken($outsider->email))->getJson("/api/v1/students/{$anu->student_id}/attendance")
            ->assertForbidden();
    }
}
