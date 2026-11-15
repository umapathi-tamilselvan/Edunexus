<?php

namespace Tests\Feature\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    private string $adminToken;

    private AcademicYear $year;

    private SchoolClass $class;

    private Section $sectionA;

    private Section $sectionB;

    private Period $period;

    private Subject $maths;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->year = AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']);
        $this->sectionA = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'A']);
        $this->sectionB = Section::factory()->for($this->class, 'schoolClass')->create(['name' => 'B']);
        $this->period = Period::factory()->for($this->year)->create(['name' => 'Period 1']);
        $this->maths = Subject::factory()->create(['name' => 'Mathematics']);
        $this->class->subjects()->attach($this->maths);
        $this->teacher = $this->createUser(Role::Teacher, ['name' => 'Meena']);
    }

    private function setSlot(Section $section, int $weekday, Period $period, Subject $subject, ?User $teacher = null): TestResponse
    {
        return $this->withToken($this->adminToken)->putJson(
            "/api/v1/sections/{$section->id}/timetable/{$weekday}/{$period->id}",
            ['subject_id' => $subject->id, 'teacher_id' => $teacher?->id],
        );
    }

    private function makeSubjectTeacher(Section $section, User $teacher): void
    {
        $teacher->subjects()->syncWithoutDetaching($this->maths);
        $this->assignSubjectTeacher($section, $teacher)->assertOk();
    }

    private function assignSubjectTeacher(Section $section, ?User $teacher): TestResponse
    {
        return $this->withToken($this->adminToken)->putJson("/api/v1/sections/{$section->id}/subjects/{$this->maths->id}/teacher", ['teacher_id' => $teacher?->id]);
    }

    private function assertSlot(int $slotId, ?User $teacher, bool $isOverride): void
    {
        $slot = TimetableEntry::query()->findOrFail($slotId);

        $this->assertSame([$teacher?->id, $isOverride], [$slot->teacher_id, $slot->is_override]);
    }

    public function test_the_bell_schedule_is_managed_per_year(): void
    {
        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$this->year->id}/periods", ['name' => 'Lunch', 'starts_at' => '12:30', 'ends_at' => '13:00', 'is_break' => true, 'sort_order' => 5])
            ->assertCreated()
            ->assertJsonPath('data.is_break', true)
            ->assertJsonPath('data.starts_at', '12:30');

        $this->withToken($this->adminToken)->postJson("/api/v1/academic-years/{$this->year->id}/periods", ['name' => 'Period 1', 'starts_at' => '10:00', 'ends_at' => '09:00'])
            ->assertJsonValidationErrors([
                'name' => 'The name has already been taken.',
                'ends_at' => 'The ends at field must be a date after starts at.',
            ]);

        $this->withToken($this->adminToken)->getJson("/api/v1/academic-years/{$this->year->id}/periods")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_slot_is_set_replaced_and_cleared(): void
    {
        $science = Subject::factory()->create();
        $this->class->subjects()->attach($science);

        $this->setSlot($this->sectionA, 1, $this->period, $this->maths, $this->teacher)->assertSuccessful()
            ->assertJsonPath('data.subject.name', 'Mathematics')
            ->assertJsonPath('data.teacher.name', 'Meena');
        $this->setSlot($this->sectionA, 1, $this->period, $science)->assertSuccessful();

        $this->assertSame($science->id, TimetableEntry::query()->sole()->subject_id);

        $this->withToken($this->adminToken)->deleteJson("/api/v1/sections/{$this->sectionA->id}/timetable/1/{$this->period->id}")->assertNoContent();
        $this->assertSame(0, TimetableEntry::query()->count());
    }

    public function test_a_teacher_cannot_be_in_two_sections_at_once(): void
    {
        $this->setSlot($this->sectionA, 1, $this->period, $this->maths, $this->teacher)->assertSuccessful();

        $this->setSlot($this->sectionB, 1, $this->period, $this->maths, $this->teacher)
            ->assertJsonValidationErrors(['teacher_id' => 'Meena already teaches Class 5 A on Monday in Period 1.']);

        $this->setSlot($this->sectionB, 2, $this->period, $this->maths, $this->teacher)->assertSuccessful();
    }

    public function test_the_subject_must_be_offered_to_the_class(): void
    {
        $art = Subject::factory()->create(['name' => 'Art']);

        $this->setSlot($this->sectionA, 1, $this->period, $art)
            ->assertJsonValidationErrors(['subject_id' => 'Art is not offered to Class 5. Add it to the class\'s subjects first.']);
    }

    public function test_breaks_and_other_years_periods_cannot_be_used(): void
    {
        $lunch = Period::factory()->for($this->year)->break()->create();
        $otherYear = Period::factory()->create();

        $this->setSlot($this->sectionA, 1, $lunch, $this->maths)->assertJsonValidationErrors(['period_id' => 'Lunch is a break.']);
        $this->setSlot($this->sectionA, 1, $otherYear, $this->maths)->assertJsonValidationErrors(['period_id' => 'This period belongs to another academic year.']);
    }

    public function test_the_weekday_must_be_one_to_seven(): void
    {
        $this->withToken($this->adminToken)->putJson("/api/v1/sections/{$this->sectionA->id}/timetable/8/{$this->period->id}", ['subject_id' => $this->maths->id])->assertNotFound();
    }

    public function test_a_period_in_use_cannot_be_deleted_or_turned_into_a_break(): void
    {
        $this->setSlot($this->sectionA, 1, $this->period, $this->maths)->assertSuccessful();

        $this->withToken($this->adminToken)->deleteJson("/api/v1/periods/{$this->period->id}")
            ->assertJsonValidationErrors(['period' => 'This period is used in the timetable. Clear those slots first.']);
        $this->withToken($this->adminToken)->patchJson("/api/v1/periods/{$this->period->id}", ['name' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:45', 'is_break' => true])
            ->assertJsonValidationErrors(['is_break' => 'Clear this period from the timetable before making it a break.']);
    }

    public function test_a_sections_timetable_lists_its_periods_and_entries(): void
    {
        $this->setSlot($this->sectionA, 3, $this->period, $this->maths, $this->teacher)->assertSuccessful();

        $this->withToken($this->adminToken)->getJson("/api/v1/sections/{$this->sectionA->id}/timetable")
            ->assertOk()
            ->assertJsonPath('data.section.class', 'Class 5')
            ->assertJsonPath('data.periods.0.name', 'Period 1')
            ->assertJsonPath('data.entries.0.weekday', 3)
            ->assertJsonPath('data.entries.0.teacher.name', 'Meena');
    }

    public function test_a_teacher_sees_their_own_teaching_timetable(): void
    {
        $this->setSlot($this->sectionB, 2, $this->period, $this->maths, $this->teacher)->assertSuccessful();

        $this->withToken($this->issueToken($this->teacher->email))->getJson('/api/v1/me/timetable')
            ->assertOk()
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.section.name', 'B')
            ->assertJsonPath('data.entries.0.section.class', 'Class 5');
    }

    public function test_teachers_also_see_students_of_sections_they_teach_and_no_others(): void
    {
        $taught = StudentEnrollment::factory()->create(['section_id' => $this->sectionB->id, 'roll_no' => 1])->student;
        StudentEnrollment::factory()->create(['section_id' => $this->sectionA->id, 'roll_no' => 1]);
        $teacherToken = $this->issueToken($this->teacher->email);

        $this->withToken($teacherToken)->getJson('/api/v1/students')->assertOk()->assertJsonCount(0, 'data');

        $this->setSlot($this->sectionB, 1, $this->period, $this->maths, $this->teacher)->assertSuccessful();

        $this->withToken($teacherToken)->getJson('/api/v1/students')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $taught->id);
    }

    public function test_a_slot_without_a_teacher_gets_the_subject_teacher_and_any_other_teacher_is_an_override(): void
    {
        $this->makeSubjectTeacher($this->sectionA, $this->teacher);
        $period2 = Period::factory()->for($this->year)->create(['name' => 'Period 2']);
        $period3 = Period::factory()->for($this->year)->create(['name' => 'Period 3']);

        $this->setSlot($this->sectionA, 1, $this->period, $this->maths)
            ->assertJsonPath('data.teacher.name', 'Meena')
            ->assertJsonPath('data.is_override', false);
        $this->setSlot($this->sectionA, 1, $period2, $this->maths, $this->teacher)
            ->assertJsonPath('data.is_override', false);
        $this->setSlot($this->sectionA, 1, $period3, $this->maths, $this->createUser(Role::Teacher, ['name' => 'Ravi']))
            ->assertJsonPath('data.teacher.name', 'Ravi')
            ->assertJsonPath('data.is_override', true);
    }

    public function test_slots_follow_a_new_subject_teacher_except_overrides(): void
    {
        $this->makeSubjectTeacher($this->sectionA, $this->teacher);
        $ravi = $this->createUser(Role::Teacher, ['name' => 'Ravi']);
        $ravi->subjects()->attach($this->maths);
        $period2 = Period::factory()->for($this->year)->create(['name' => 'Period 2']);
        $period3 = Period::factory()->for($this->year)->create(['name' => 'Period 3']);
        $usha = $this->createUser(Role::Teacher, ['name' => 'Usha']);
        $following = $this->setSlot($this->sectionA, 1, $this->period, $this->maths)->json('data.id');
        $override = $this->setSlot($this->sectionA, 1, $period2, $this->maths, $usha)->json('data.id');
        $setToRavi = $this->setSlot($this->sectionA, 1, $period3, $this->maths, $ravi)->json('data.id');

        $this->assignSubjectTeacher($this->sectionA, $ravi)->assertOk();

        $this->assertSlot($following, $ravi, false);
        $this->assertSlot($override, $usha, true);
        $this->assertSlot($setToRavi, $ravi, false);

        $this->assignSubjectTeacher($this->sectionA, null)->assertOk();

        $this->assertSlot($following, null, false);
        $this->assertSlot($override, $usha, true);
        $this->assertSlot($setToRavi, null, false);
    }

    public function test_a_new_subject_teacher_busy_in_a_following_slot_changes_nothing(): void
    {
        $this->makeSubjectTeacher($this->sectionA, $this->teacher);
        $ravi = $this->createUser(Role::Teacher, ['name' => 'Ravi']);
        $ravi->subjects()->attach($this->maths);
        $slot = $this->setSlot($this->sectionA, 1, $this->period, $this->maths)->json('data.id');
        $this->setSlot($this->sectionB, 1, $this->period, $this->maths, $ravi)->assertSuccessful();

        $this->assignSubjectTeacher($this->sectionA, $ravi)
            ->assertJsonValidationErrors(['teacher_id' => 'Ravi already teaches Class 5 B on Monday in Period 1.']);

        $this->assertSlot($slot, $this->teacher, false);
        $this->assertSame($this->teacher->id, $this->sectionA->subjectTeachers()->sole()->teacher_id);
    }

    public function test_teachers_cannot_edit_the_timetable(): void
    {
        $this->withToken($this->issueToken($this->teacher->email))
            ->putJson("/api/v1/sections/{$this->sectionA->id}/timetable/1/{$this->period->id}", ['subject_id' => $this->maths->id])
            ->assertForbidden();
    }
}
