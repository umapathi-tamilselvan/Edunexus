<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Tests\TestCase;

/**
 * The teachers list behind every teacher picker, and each teacher's
 * workload in the selected year.
 */
class TeacherTest extends TestCase
{
    public function test_every_teacher_is_listed_with_their_subjects_however_many_users_there_are(): void
    {
        User::factory()->count(55)->create()->each(fn (User $teacher) => $teacher->assignRole(Role::Teacher->value));
        $principal = $this->createUser(Role::Principal, ['name' => 'Principal Who Teaches']);
        $principal->subjects()->attach(Subject::factory()->create(['name' => 'Mathematics']));
        $this->createUser(Role::Accountant, ['name' => 'Accountant']);

        $teachers = $this->withToken($this->issueToken($this->createUser(Role::Admin)->email))->getJson('/api/v1/teachers')
            ->assertOk()
            ->assertJsonCount(56, 'data')
            ->json('data');

        $this->assertSame(['Mathematics'], array_column(collect($teachers)->firstWhere('name', 'Principal Who Teaches')['subjects'], 'name'));
        $this->assertNotContains('Accountant', array_column($teachers, 'name'));
    }

    public function test_the_workload_counts_each_teachers_periods_and_section_subjects_in_the_selected_year(): void
    {
        $year = AcademicYear::factory()->current()->create();
        $meena = $this->createUser(Role::Teacher, ['name' => 'Meena']);
        $idle = $this->createUser(Role::Teacher, ['name' => 'Idle Teacher']);
        $section = Section::factory()->for(SchoolClass::factory()->for($year), 'schoolClass')->create();
        $periods = Period::factory()->count(2)->for($year)->create();
        Period::factory()->for($year)->break()->create();
        $this->slot($section, Weekday::Monday, $periods[0], $meena);
        $this->slot($section, Weekday::Monday, $periods[1], $meena);
        $this->slot($section, Weekday::Wednesday, $periods[0], $meena);
        SectionSubjectTeacher::factory()->for($section)->create(['teacher_id' => $meena->id]);
        // Last year's teaching doesn't count.
        $lastYearSection = Section::factory()->for(SchoolClass::factory(), 'schoolClass')->create();
        $this->slot($lastYearSection, Weekday::Monday, Period::factory()->for($lastYearSection->schoolClass->academicYear)->create(), $meena);

        $workload = $this->withToken($this->issueToken($meena->email))->getJson('/api/v1/teachers/workload')
            ->assertOk()
            ->assertJsonPath('data.academic_year.id', $year->id)
            ->assertJsonPath('data.periods_per_day', 2)
            ->json('data.teachers');

        $this->assertSame(
            [
                ['name' => 'Idle Teacher', 'section_subjects' => 0, 'periods' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0], 'total' => 0],
                ['name' => 'Meena', 'section_subjects' => 1, 'periods' => [1 => 2, 2 => 0, 3 => 1, 4 => 0, 5 => 0, 6 => 0, 7 => 0], 'total' => 3],
            ],
            array_map(fn (array $teacher): array => array_intersect_key($teacher, array_flip(['name', 'section_subjects', 'periods', 'total'])), $workload),
        );
        $this->assertSame([$idle->id, $meena->id], array_column($workload, 'id'));
    }

    public function test_guardians_cannot_list_teachers_or_their_workload(): void
    {
        $token = $this->issueToken($this->createUser(Role::Guardian)->email);

        $this->withToken($token)->getJson('/api/v1/teachers')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/teachers/workload')->assertForbidden();
    }

    private function slot(Section $section, Weekday $weekday, Period $period, User $teacher): void
    {
        TimetableEntry::factory()->for($section)->create(['weekday' => $weekday, 'period_id' => $period->id, 'teacher_id' => $teacher->id]);
    }
}
