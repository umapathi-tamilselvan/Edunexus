<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\Sequences;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * The sample school for development clients: two academic years of Class 1
 * to Class 10, sections A to C with class teachers, subjects, and 50
 * students per class who move up a class each year.
 *
 * Each seeding inserts about 3,000 rows, so related checks share one run.
 */
class DemoSchoolSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The demo years are this year and last, from the school's June start.
        $this->travelTo('2026-10-26');
    }

    public function test_creates_two_years_of_ten_classes_with_three_sections_class_teachers_and_subjects(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $years = AcademicYear::query()->with(['classes.sections.classTeacher', 'classes.subjects'])->orderBy('starts_on')->get();
        $this->assertSame(['2025-26', '2026-27'], $years->pluck('name')->all());
        $this->assertSame('2026-27', AcademicYear::query()->current()->sole()->name);

        foreach ($years as $year) {
            $this->assertSame(
                ['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'],
                $year->classes->pluck('name')->all(),
            );
            $this->assertSame([['A', 'B', 'C']], $year->classes->map(fn ($class): array => $class->sections->pluck('name')->all())->unique()->values()->all());

            $classes = $year->classes->keyBy('name');
            $this->assertSame(['CSC', 'ENG', 'EVS', 'HIN', 'MAT', 'TAM'], $classes['Class 1']->subjects->pluck('code')->all());
            $this->assertSame(['CSC', 'ENG', 'HIN', 'MAT', 'SCI', 'SST', 'TAM'], $classes['Class 10']->subjects->pluck('code')->all());

            $this->assertSame('teacher01@test.test', $classes['Class 1']->sections[0]->classTeacher?->email);
            $this->assertSame('teacher02@test.test', $classes['Class 1']->sections[1]->classTeacher?->email);
            $this->assertSame('teacher30@test.test', $classes['Class 10']->sections[2]->classTeacher?->email);
            $this->assertCount(30, $year->classes->flatMap->sections->pluck('class_teacher_id')->filter()->unique());
        }

        $this->assertSame(30, User::role(Role::Teacher->value)->count());
    }

    public function test_teachers_teach_one_or_two_subjects_and_every_section_has_a_teacher_for_each_subject(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $subjectsTaught = User::query()->withCount('subjects')->pluck('subjects_count');
        $this->assertSame([1, 2], $subjectsTaught->unique()->sort()->values()->all());
        $this->assertSame(45, $subjectsTaught->sum());

        $sections = Section::query()->with(['schoolClass.subjects', 'subjectTeachers.teacher.subjects'])->get();
        $this->assertCount(60, $sections);

        foreach ($sections as $section) {
            $this->assertEqualsCanonicalizing($section->schoolClass->subjects->modelKeys(), $section->subjectTeachers->pluck('subject_id')->all());

            foreach ($section->subjectTeachers as $assignment) {
                $this->assertContains($assignment->subject_id, $assignment->teacher->subjects->modelKeys());
            }
        }

        // Spread out: in a year, no teacher has more than 12 of the 195 section subjects.
        $loads = $sections->groupBy('schoolClass.academic_year_id')->map(fn ($yearSections) => $yearSections->flatMap->subjectTeachers->countBy('teacher_id'));
        $this->assertLessThanOrEqual(12, $loads->flatten()->max());

        $this->assertSame(
            ['Period 1', 'Period 2', 'Short break', 'Period 3', 'Period 4', 'Lunch', 'Period 5', 'Period 6', 'Period 7', 'Period 8'],
            AcademicYear::query()->current()->sole()->periods()->pluck('name')->all(),
        );
        $this->assertSame(16, Period::query()->where('is_break', false)->count());
    }

    public function test_fills_every_class_with_fifty_students_who_move_up_a_class_the_next_year(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $sectionSizes = Section::query()
            ->with('schoolClass.academicYear')
            ->withCount('enrollments')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Section $section): string => "{$section->schoolClass->academicYear->name} {$section->schoolClass->name}")
            ->map(fn ($sections): array => $sections->pluck('enrollments_count')->all());
        $this->assertCount(20, $sectionSizes);
        $this->assertSame([[17, 17, 16]], $sectionSizes->unique()->values()->all());

        $statuses = StudentEnrollment::query()
            ->with(['academicYear', 'section.schoolClass'])
            ->get()
            ->countBy(fn (StudentEnrollment $enrollment): string => "{$enrollment->academicYear->name} {$enrollment->section->schoolClass->name} {$enrollment->status->value}");
        $this->assertSame(50, $statuses['2025-26 Class 3 promoted']);
        $this->assertSame(50, $statuses['2025-26 Class 10 left']);
        $this->assertSame(500, $statuses->filter(fn (int $count, string $key): bool => str_starts_with($key, '2026-27') && str_ends_with($key, ' active'))->sum());

        $this->assertSame($this->studentIdsIn('2025-26', 'Class 3', 'B'), $this->studentIdsIn('2026-27', 'Class 4', 'B'));
        $this->assertSame(0, Student::query()->whereKey($this->studentIdsIn('2026-27', 'Class 1', 'A'))->has('enrollments', '>', 1)->count());
        $this->assertDatabaseCount('students', 550);
    }

    public function test_numbers_rolls_alphabetically_and_gives_each_student_a_father_and_a_mother(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $register = $this->section('2026-27', 'Class 4', 'B')->enrollments()->with('student.guardians')->orderBy('roll_no')->get();
        $this->assertSame(range(1, 17), $register->pluck('roll_no')->all());
        $names = $register->pluck('student.name')->all();
        $alphabetical = $names;
        sort($alphabetical);
        $this->assertSame($alphabetical, $names);

        $guardians = $register->first()->student->guardians;
        $this->assertSame([GuardianRelation::Father->value, GuardianRelation::Mother->value], $guardians->pluck('pivot.relation')->all());
        $this->assertSame([1, 0], $guardians->pluck('pivot.is_primary')->all());
        $this->assertMatchesRegularExpression('/^9\d{9}$/', $guardians[0]->phone);
        $this->assertTrue($guardians[0]->is(Guardian::query()->firstWhere('phone_hash', Guardian::hashPhone($guardians[0]->phone))));
    }

    public function test_a_teacher_signs_in_with_the_demo_password_and_lists_only_their_sections_students(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $token = $this->postJson('/api/v1/auth/tokens', [
            'email' => 'teacher01@test.test',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertCreated()->json('token');

        // Class 1 A as class teacher, and every section they teach a subject to, this year.
        $teacherId = User::query()->where('email', 'teacher01@test.test')->value('id');
        $taughtSectionIds = Section::query()
            ->whereHas('schoolClass.academicYear', fn (Builder $year) => $year->where('is_current', true))
            ->where(fn (Builder $sections) => $sections->where('class_teacher_id', $teacherId)->orWhereHas('subjectTeachers', fn (Builder $teachers) => $teachers->where('teacher_id', $teacherId)))
            ->pluck('id');
        $this->assertContains($this->section('2026-27', 'Class 1', 'A')->id, $taughtSectionIds);

        $listed = $this->withToken($token)->getJson('/api/v1/students')->assertOk();
        $this->assertSame(StudentEnrollment::query()->whereIn('section_id', $taughtSectionIds)->count(), $listed->json('meta.total'));
        $this->assertEmpty(array_diff($listed->json('data.*.id'), StudentEnrollment::query()->whereIn('section_id', $taughtSectionIds)->pluck('student_id')->all()));
    }

    public function test_rerunning_adds_no_duplicates(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->seed(DemoSchoolSeeder::class);

        $this->assertDatabaseCount('academic_years', 2);
        $this->assertDatabaseCount('classes', 20);
        $this->assertDatabaseCount('sections', 60);
        $this->assertDatabaseCount('subjects', 8);
        $this->assertDatabaseCount('users', 30);
        $this->assertDatabaseCount('students', 550);
        $this->assertDatabaseCount('guardians', 1100);
        $this->assertDatabaseCount('student_enrollments', 1000);
        $this->assertDatabaseCount('subject_teacher', 45);
        $this->assertDatabaseCount('section_subject_teachers', 390);
        $this->assertDatabaseCount('periods', 20);
    }

    public function test_fills_an_existing_year_and_skips_admission_numbers_in_use(): void
    {
        $existingYear = AcademicYear::factory()->current()->create(['name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-04-30']);
        Student::factory()->create(['admission_no' => 'TEST00002']);

        $this->seed(DemoSchoolSeeder::class);

        $this->assertDatabaseCount('academic_years', 2);
        $this->assertSame(500, StudentEnrollment::query()->whereBelongsTo($existingYear)->count());

        $this->assertDatabaseCount('students', 551);
        $this->assertDatabaseHas('students', ['admission_no' => 'TEST00001']);
        $this->assertSame('TEST00551', Student::query()->max('admission_no'));
        $this->assertSame(552, DB::transaction(fn (): int => app(Sequences::class)->next('student.admission_no')));
    }

    public function test_writes_no_audit_rows_and_leaves_auditing_on_afterwards(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertDatabaseEmpty('audits');

        Student::factory()->create();

        $this->assertDatabaseCount('audits', 1);
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(DemoSchoolSeeder::class)->run();
            $this->fail('The demo school was seeded in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('never runs in production', $exception->getMessage());
        }

        $this->assertDatabaseEmpty('academic_years');
        $this->assertDatabaseEmpty('users');
    }

    private function section(string $year, string $class, string $name): Section
    {
        return Section::query()
            ->where('name', $name)
            ->whereHas('schoolClass', fn (Builder $query) => $query
                ->where('name', $class)
                ->whereHas('academicYear', fn (Builder $query) => $query->where('name', $year)))
            ->sole();
    }

    /**
     * @return list<int>
     */
    private function studentIdsIn(string $year, string $class, string $section): array
    {
        return $this->section($year, $class, $section)->enrollments()->orderBy('student_id')->pluck('student_id')->all();
    }
}
