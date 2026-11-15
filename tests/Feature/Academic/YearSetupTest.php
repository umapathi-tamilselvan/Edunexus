<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Enums\Role;
use Tests\TestCase;

/**
 * The Year Setup checklist reports what is missing for a year.
 */
class YearSetupTest extends TestCase
{
    private string $adminToken;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->year = AcademicYear::factory()->current()->create();
    }

    /**
     * @return array<string, array{done: bool, detail: string}>
     */
    private function steps(): array
    {
        $steps = $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$this->year->id}/setup-status")
            ->assertOk()
            ->json('data.steps');

        return collect($steps)->keyBy('key')->map(fn (array $step): array => ['done' => $step['done'], 'detail' => $step['detail']])->all();
    }

    public function test_an_empty_year_says_what_is_missing_at_every_step(): void
    {
        $steps = $this->steps();

        $this->assertFalse($steps['classes']['done']);
        $this->assertSame('No classes yet.', $steps['classes']['detail']);
        $this->assertFalse($steps['bell_schedule']['done']);
        $this->assertFalse($steps['holidays']['done']);
        // A new year has admissions switched off, so the step is not done (D44).
        $this->assertFalse($steps['admissions']['done']);
    }

    public function test_a_section_without_a_class_teacher_is_reported_until_one_is_assigned(): void
    {
        $class = SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']);
        $class->subjects()->attach(Subject::factory()->create());
        $section = Section::factory()->for($class, 'schoolClass')->create(['name' => 'A']);

        $this->assertSame('1 sections have no class teacher.', $this->steps()['class_teachers']['detail']);

        $teacher = $this->createUser(Role::Teacher);
        $section->update(['class_teacher_id' => $teacher->id]);

        $this->assertTrue($this->steps()['class_teachers']['done']);
        $this->assertTrue($this->steps()['classes']['done']);
        $this->assertTrue($this->steps()['subjects']['done']);
    }

    public function test_a_holiday_completes_the_holidays_step(): void
    {
        $this->year->holidays()->create(['date' => $this->year->starts_on->copy()->addDays(10)->toDateString(), 'name' => 'Pongal']);

        $steps = $this->steps();

        $this->assertTrue($steps['holidays']['done']);
        $this->assertSame('1 holidays added.', $steps['holidays']['detail']);
    }
}
