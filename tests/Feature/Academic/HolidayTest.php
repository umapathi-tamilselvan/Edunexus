<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Tests\TestCase;

/**
 * Holidays: days of an academic year on which no attendance is taken.
 */
class HolidayTest extends TestCase
{
    private User $admin;

    private string $adminToken;

    private AcademicYear $year;

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
        $this->section = Section::factory()->for(SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']), 'schoolClass')->create(['name' => 'A']);
    }

    private function holidaysUrl(): string
    {
        return "/api/v1/academic-years/{$this->year->id}/holidays";
    }

    public function test_a_holiday_is_added_and_listed_for_its_year(): void
    {
        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => today()->subDays(5)->toDateString(), 'name' => 'Pongal'])
            ->assertCreated()
            ->assertJsonPath('data.date', today()->subDays(5)->toDateString())
            ->assertJsonPath('data.name', 'Pongal');

        $this->withToken($this->adminToken)->getJson($this->holidaysUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pongal');
    }

    public function test_a_holiday_must_fall_inside_its_academic_year(): void
    {
        $before = $this->year->starts_on->copy()->subDay();

        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => $before->toDateString(), 'name' => 'Too early'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => $before->format('j M Y').' is not in '.$this->year->name.'.']);
    }

    public function test_the_same_day_cannot_be_a_holiday_twice(): void
    {
        $date = today()->subDays(5)->toDateString();
        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => $date, 'name' => 'Pongal'])->assertCreated();

        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => $date, 'name' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'That day is already a holiday.']);
    }

    public function test_a_day_with_attendance_cannot_become_a_holiday_until_it_is_cleared(): void
    {
        $day = today()->subDays(2)->toDateString();
        $anu = StudentEnrollment::factory()
            ->for(Student::factory()->state(['name' => 'Anu']))
            ->create(['section_id' => $this->section->id, 'roll_no' => 1]);

        $this->withToken($this->adminToken)->putJson("/api/v1/sections/{$this->section->id}/attendance", [
            'date' => $day,
            'entries' => [['enrollment_id' => $anu->id, 'status' => 'present']],
        ])->assertOk();

        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => $day, 'name' => 'Sports day'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Attendance has been taken on '.today()->subDays(2)->format('j M Y').'. Clear it before making the day a holiday.']);

        $this->withToken($this->adminToken)->putJson("/api/v1/sections/{$this->section->id}/attendance", [
            'date' => $day,
            'entries' => [['enrollment_id' => $anu->id, 'status' => null]],
        ])->assertOk();

        $this->withToken($this->adminToken)->postJson($this->holidaysUrl(), ['date' => $day, 'name' => 'Sports day'])->assertCreated();
    }

    public function test_a_teacher_can_read_holidays_but_cannot_add_or_remove_them(): void
    {
        $holiday = $this->year->holidays()->create(['date' => today()->subDays(5)->toDateString(), 'name' => 'Pongal']);
        $teacher = $this->createUser(Role::Teacher);
        $token = $this->issueToken($teacher->email);

        $this->withToken($token)->getJson($this->holidaysUrl())->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($token)->postJson($this->holidaysUrl(), ['date' => today()->subDays(6)->toDateString(), 'name' => 'Nope'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/holidays/{$holiday->id}")->assertForbidden();

        $this->assertDatabaseCount('holidays', 1);
    }

    public function test_deleting_a_holiday_makes_the_day_markable_again(): void
    {
        $day = today()->subDays(5)->toDateString();
        $holiday = $this->year->holidays()->create(['date' => $day, 'name' => 'Pongal']);

        $this->withToken($this->adminToken)->getJson("/api/v1/sections/{$this->section->id}/attendance?date={$day}")
            ->assertJsonPath('data.can_mark', false);

        $this->withToken($this->adminToken)->deleteJson("/api/v1/holidays/{$holiday->id}")->assertNoContent();

        $this->withToken($this->adminToken)->getJson("/api/v1/sections/{$this->section->id}/attendance?date={$day}")
            ->assertJsonPath('data.can_mark', true)
            ->assertJsonPath('data.closed_reason', null);
    }
}
