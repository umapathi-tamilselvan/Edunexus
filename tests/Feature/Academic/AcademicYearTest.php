<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Actions\CreateAcademicYear;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Enums\Role;
use App\Support\OrganizationContext;
use App\Support\Settings\Setting;
use Database\Seeders\AcademicYearSeeder;
use Tests\TestCase;

/**
 * Academic years come from the client's start month: the first is opened
 * automatically and none is created or edited by hand.
 */
class AcademicYearTest extends TestCase
{
    public function test_the_first_year_is_the_one_today_falls_in_and_becomes_current(): void
    {
        $this->travelTo('2026-10-26');

        $this->seed(AcademicYearSeeder::class);

        $year = AcademicYear::query()->sole();
        $this->assertSame('2026-27', $year->name);
        $this->assertSame('2026-06-01', $year->starts_on->toDateString());
        $this->assertSame('2027-05-31', $year->ends_on->toDateString());
        $this->assertTrue($year->is_current);
    }

    public function test_before_the_start_month_today_falls_in_the_year_that_started_the_calendar_year_before(): void
    {
        $this->travelTo('2027-03-15');

        $this->seed(AcademicYearSeeder::class);

        $this->assertSame('2026-27', AcademicYear::query()->sole()->name);
    }

    public function test_the_first_year_starts_in_the_clients_own_start_month(): void
    {
        $this->setYearStartMonth(4);
        $this->travelTo('2026-10-26');

        $this->seed(AcademicYearSeeder::class);

        $year = AcademicYear::query()->sole();
        $this->assertSame('2026-04-01', $year->starts_on->toDateString());
        $this->assertSame('2027-03-31', $year->ends_on->toDateString());
    }

    public function test_a_year_starting_in_january_is_named_after_its_calendar_year(): void
    {
        $this->setYearStartMonth(1);
        $this->travelTo('2026-10-26');

        $this->seed(AcademicYearSeeder::class);

        $year = AcademicYear::query()->sole();
        $this->assertSame('2026', $year->name);
        $this->assertSame('2026-12-31', $year->ends_on->toDateString());
    }

    public function test_the_seeder_leaves_a_client_that_already_has_a_year_alone(): void
    {
        AcademicYear::factory()->current()->create(['name' => '2020-21']);

        $this->seed(AcademicYearSeeder::class);

        $this->assertSame(['2020-21'], AcademicYear::query()->pluck('name')->all());
    }

    public function test_a_year_starts_after_the_year_before_it_ends_and_ends_before_the_year_after_it_starts(): void
    {
        AcademicYear::factory()->current()->create(['name' => 'Long year', 'starts_on' => '2026-04-01', 'ends_on' => '2027-06-30']);
        AcademicYear::factory()->create(['name' => 'Early year', 'starts_on' => '2028-05-01', 'ends_on' => '2029-04-30']);

        $year = app(CreateAcademicYear::class)->handle(2027);

        $this->assertSame('2027-28', $year->name);
        $this->assertSame('2027-07-01', $year->starts_on->toDateString());
        $this->assertSame('2028-04-30', $year->ends_on->toDateString());
        $this->assertFalse($year->is_current);
    }

    public function test_a_year_is_created_only_once(): void
    {
        app(CreateAcademicYear::class)->handle(2026);

        $this->expectExceptionMessage('The academic year 2026-27 already exists.');

        app(CreateAcademicYear::class)->handle(2026);
    }

    public function test_the_next_year_copies_the_current_years_classes_sections_subjects_and_bell_schedule(): void
    {
        $current = AcademicYear::factory()->current()->create(['name' => '2026-27', 'starts_on' => '2026-06-01', 'ends_on' => '2027-05-31']);
        $class = SchoolClass::factory()->for($current)->create(['name' => 'Class 1', 'sort_order' => 1]);
        $class->subjects()->attach($maths = Subject::factory()->create());
        $section = Section::factory()->for($class, 'schoolClass')->create(['name' => 'A', 'capacity' => 35, 'class_teacher_id' => $this->createUser()->id]);
        SectionSubjectTeacher::factory()->for($section)->for($maths)->create();
        $period = Period::factory()->for($current)->create(['name' => 'Period 1', 'starts_at' => '08:45', 'ends_at' => '09:30', 'sort_order' => 1]);
        Period::factory()->for($current)->create(['name' => 'Lunch', 'starts_at' => '12:30', 'ends_at' => '13:00', 'is_break' => true, 'sort_order' => 5]);
        TimetableEntry::factory()->for($section)->for($maths)->create(['period_id' => $period->id]);

        $this->withToken($this->adminToken())->postJson('/api/v1/academic-years/next')
            ->assertCreated()
            ->assertJsonPath('data.name', '2027-28')
            ->assertJsonPath('data.starts_on', '2027-06-01')
            ->assertJsonPath('data.ends_on', '2028-05-31')
            ->assertJsonPath('data.is_current', false);

        $next = AcademicYear::query()->where('name', '2027-28')->sole();
        $copiedClass = $next->classes()->with(['sections.subjectTeachers', 'sections.timetableEntries', 'subjects'])->sole();
        $this->assertSame(['Class 1', 1], [$copiedClass->name, $copiedClass->sort_order]);
        $this->assertSame([$maths->id], $copiedClass->subjects->modelKeys());
        $copiedSection = $copiedClass->sections->sole();
        $this->assertSame(['A', 35, null], [$copiedSection->name, $copiedSection->capacity, $copiedSection->class_teacher_id]);
        $this->assertTrue($copiedSection->subjectTeachers->isEmpty());
        $this->assertTrue($copiedSection->timetableEntries->isEmpty());
        $this->assertSame(
            [['Period 1', '08:45:00', '09:30:00', false], ['Lunch', '12:30:00', '13:00:00', true]],
            $next->periods()->get()->map(fn (Period $period): array => [$period->name, $period->starts_at, $period->ends_at, $period->is_break])->all(),
        );
        $this->assertTrue($current->fresh()->is_current);
    }

    public function test_only_one_year_can_be_opened_ahead_of_the_current_one(): void
    {
        $current = AcademicYear::factory()->current()->create(['name' => '2026-27', 'starts_on' => '2026-06-01', 'ends_on' => '2027-05-31']);
        AcademicYear::factory()->create(['name' => '2027-28', 'starts_on' => '2027-06-01', 'ends_on' => '2028-05-31']);

        $this->withToken($this->adminToken())->postJson('/api/v1/academic-years/next')
            ->assertJsonValidationErrors(['academic_year' => 'The next academic year, 2027-28, already exists.']);

        $this->assertDatabaseCount('academic_years', 2);
        $this->assertTrue($current->fresh()->is_current);
    }

    public function test_teachers_cannot_open_the_next_year(): void
    {
        AcademicYear::factory()->current()->create();

        $this->withToken($this->issueToken($this->createUser(Role::Teacher)->email))->postJson('/api/v1/academic-years/next')->assertForbidden();

        $this->assertDatabaseCount('academic_years', 1);
    }

    public function test_years_are_no_longer_created_or_edited_through_the_api(): void
    {
        $year = AcademicYear::factory()->current()->create(['name' => '2026-27']);
        $token = $this->adminToken();

        $this->withToken($token)->postJson('/api/v1/academic-years', ['name' => '2027-28', 'starts_on' => '2027-06-01', 'ends_on' => '2028-05-31'])->assertMethodNotAllowed();
        $this->withToken($token)->patchJson("/api/v1/academic-years/{$year->id}", ['name' => 'Renamed'])->assertMethodNotAllowed();

        $this->assertSame(['2026-27'], AcademicYear::query()->pluck('name')->all());
    }

    private function adminToken(): string
    {
        return $this->issueToken($this->createUser(Role::Admin)->email);
    }

    private function setYearStartMonth(int $month): void
    {
        Setting::query()->updateOrCreate(['key' => OrganizationContext::YEAR_START_MONTH], ['value' => $month]);
    }
}
