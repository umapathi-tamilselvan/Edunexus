<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Actions\CreateAcademicYear;
use App\Domain\Academic\Models\AcademicYear;
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

    public function test_years_are_no_longer_created_or_edited_through_the_api(): void
    {
        $year = AcademicYear::factory()->current()->create(['name' => '2026-27']);
        $token = $this->issueToken($this->createUser(Role::Admin)->email);

        $this->withToken($token)->postJson('/api/v1/academic-years', ['name' => '2027-28', 'starts_on' => '2027-06-01', 'ends_on' => '2028-05-31'])->assertMethodNotAllowed();
        $this->withToken($token)->patchJson("/api/v1/academic-years/{$year->id}", ['name' => 'Renamed'])->assertMethodNotAllowed();

        $this->assertSame(['2026-27'], AcademicYear::query()->pluck('name')->all());
    }

    private function setYearStartMonth(int $month): void
    {
        Setting::query()->updateOrCreate(['key' => OrganizationContext::YEAR_START_MONTH], ['value' => $month]);
    }
}
