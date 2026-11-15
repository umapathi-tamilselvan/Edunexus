<?php

namespace Database\Seeders;

use App\Domain\Academic\Actions\CreateAcademicYear;
use App\Domain\Academic\Actions\MakeAcademicYearCurrent;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\OrganizationContext;
use App\Support\Sequences;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Fills a development client with a sample school, so every screen has
 * realistic data. DatabaseSeeder calls it only when APP_ENV is local, so
 * real clients never get it; run it on its own with:
 *
 *     php artisan db:seed --class=DemoSchoolSeeder
 *     EDUNEXUS_CLIENT=abc php artisan db:seed --class=DemoSchoolSeeder
 *
 * It creates:
 * - the academic year today falls in, current, and the year before it
 *   (e.g. 2025-26 and 2026-27), from the client's start month;
 * - Class 1 to Class 10 in each year, each with sections A, B and C;
 * - subjects, offered to the classes that study them, and each year's bell
 *   schedule of eight periods with a short break and lunch;
 * - 30 teachers, teacher01@<client code>.test to teacher30@…, all with the
 *   password "password". Each is class teacher of the same section in both
 *   years: teacher01 of Class 1 A, teacher02 of Class 1 B, … teacher30 of
 *   Class 10 C. Each teaches one subject, every other one a second, and
 *   every section has a subject teacher for each of its class's subjects;
 * - 50 students per class, each with a father and a mother as guardians.
 *   Last year's students move up a class this year, its Class 10 leaves,
 *   and a new Class 1 joins.
 *
 * Students, guardians and enrollments are inserted in bulk, a class at a
 * time: CreateStudent and EnrollStudent row by row took about 25 seconds.
 *
 * Safe to rerun: existing years, classes, sections, subjects, periods,
 * teachers and subject teachers are reused, and classes are only topped up
 * to 50 students. There is no timetable: 30 teachers are too few to give
 * 30 sections a full week without clashes.
 */
class DemoSchoolSeeder extends Seeder
{
    private const HIGHEST_CLASS = 10;

    private const SECTIONS = ['A', 'B', 'C'];

    private const SECTION_CAPACITY = 40;

    private const STUDENTS_PER_CLASS = 50;

    private const TEACHER_PASSWORD = 'password';

    /**
     * Code => [name, first class, last class that studies it].
     *
     * @var array<string, array{string, int, int}>
     */
    private const SUBJECTS = [
        'ENG' => ['English', 1, 10],
        'TAM' => ['Tamil', 1, 10],
        'HIN' => ['Hindi', 1, 10],
        'MAT' => ['Mathematics', 1, 10],
        'EVS' => ['Environmental Studies', 1, 5],
        'SCI' => ['Science', 6, 10],
        'SST' => ['Social Science', 6, 10],
        'CSC' => ['Computer Science', 1, 10],
    ];

    /**
     * The bell schedule: name, starts at, ends at. Anything but a period is a break.
     *
     * @var list<array{string, string, string}>
     */
    private const PERIODS = [
        ['Period 1', '08:45', '09:30'],
        ['Period 2', '09:30', '10:15'],
        ['Short break', '10:15', '10:30'],
        ['Period 3', '10:30', '11:15'],
        ['Period 4', '11:15', '12:00'],
        ['Lunch', '12:00', '12:40'],
        ['Period 5', '12:40', '13:25'],
        ['Period 6', '13:25', '14:10'],
        ['Period 7', '14:10', '14:55'],
        ['Period 8', '14:55', '15:40'],
    ];

    private Generator $faker;

    public function __construct(
        private OrganizationContext $organization,
        private Sequences $sequences,
        private CreateAcademicYear $createAcademicYear,
        private MakeAcademicYearCurrent $makeAcademicYearCurrent,
        private CreateUser $createUser,
    ) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSchoolSeeder creates sample students and teacher logins with a known password, so it never runs in production.');
        }

        $this->faker = fake('en_IN');

        // Sample data is not anyone's activity, so it stays out of the audit trail.
        Student::withoutAuditing(fn () => DB::transaction(function (): void {
            $teachers = $this->teachers();
            $subjectIds = $this->subjectIdsByClass();
            $teachersBySubject = $this->teachSubjects($teachers);
            $currentStartYear = $this->createAcademicYear->startYearOf(today());
            $previousSections = [];

            foreach ([$currentStartYear - 1, $currentStartYear] as $startYear) {
                // A year is found by when it starts, so years named by hand are reused.
                $year = AcademicYear::query()->whereYear('starts_on', $startYear)->orderBy('starts_on')->first()
                    ?? $this->createAcademicYear->handle($startYear);

                $sections = $this->classes($year, $teachers, $subjectIds);
                $this->assignSubjectTeachers($sections, $subjectIds, $teachersBySubject);
                $this->bellSchedule($year);
                $this->fillClasses($year, $sections, $previousSections);
                $previousSections = $sections;
            }

            $this->makeAcademicYearCurrent->handle($year);
        }), globally: true);
    }

    /**
     * One teacher per section of a year, in class then section order.
     *
     * @return list<User>
     */
    private function teachers(): array
    {
        // Hashed once rather than per teacher: bcrypt is slow on purpose.
        $password = Hash::make(self::TEACHER_PASSWORD);
        $teachers = [];

        foreach (range(1, self::HIGHEST_CLASS * count(self::SECTIONS)) as $number) {
            $email = sprintf('teacher%02d@%s.test', $number, $this->organization->code());

            $teachers[] = User::query()->firstWhere('email', $email)
                ?? $this->createUser->handle($this->faker->name(), $email, $password, [Role::Teacher->value]);
        }

        return $teachers;
    }

    /**
     * Creates the subjects and works out which classes study each.
     *
     * @return array<int, list<int>> subject ids by class number
     */
    private function subjectIdsByClass(): array
    {
        $subjectIds = [];

        foreach (self::SUBJECTS as $code => [$name, $firstClass, $lastClass]) {
            $subject = Subject::query()->firstOrCreate(['code' => $code], ['name' => $name]);

            foreach (range($firstClass, $lastClass) as $grade) {
                $subjectIds[$grade][] = $subject->id;
            }
        }

        return $subjectIds;
    }

    /**
     * Gives every teacher one subject, in subject order, and every other
     * teacher a second one, so each subject has several teachers.
     *
     * @param  list<User>  $teachers
     * @return array<int, list<User>> teachers by subject id
     */
    private function teachSubjects(array $teachers): array
    {
        $subjectIds = Subject::query()->whereIn('code', array_keys(self::SUBJECTS))->pluck('id', 'code');
        $codes = array_keys(self::SUBJECTS);
        $teachersBySubject = [];

        foreach ($teachers as $index => $teacher) {
            $taught = [$subjectIds[$codes[$index % count($codes)]]];

            if ($index % 2 === 1) {
                $taught[] = $subjectIds[$codes[($index + 3) % count($codes)]];
            }

            $teacher->subjects()->syncWithoutDetaching($taught);
            // Kept for the previous release, which reads one subject per teacher.
            $teacher->subject_id ??= $taught[0];
            $teacher->save();

            foreach ($taught as $subjectId) {
                $teachersBySubject[$subjectId][] = $teacher;
            }
        }

        return $teachersBySubject;
    }

    /**
     * Gives each section a teacher for every subject its class studies,
     * spreading a subject's sections evenly over the teachers who teach it.
     * Teachers already assigned are kept.
     *
     * @param  array<int, array<string, Section>>  $sections
     * @param  array<int, list<int>>  $subjectIds
     * @param  array<int, list<User>>  $teachersBySubject
     */
    private function assignSubjectTeachers(array $sections, array $subjectIds, array $teachersBySubject): void
    {
        $sectionIds = collect($sections)->flatten(1)->map(fn (Section $section): int => $section->id)->all();
        $assigned = SectionSubjectTeacher::query()->whereIn('section_id', $sectionIds)->get(['section_id', 'subject_id', 'teacher_id']);
        $load = $assigned->countBy('teacher_id')->all();
        $taken = $assigned->map(fn (SectionSubjectTeacher $assignment): string => "{$assignment->section_id}-{$assignment->subject_id}")->flip()->all();
        $assignments = [];

        foreach ($sections as $grade => $classSections) {
            foreach ($classSections as $section) {
                foreach ($subjectIds[$grade] as $subjectId) {
                    if (isset($taken["{$section->id}-{$subjectId}"])) {
                        continue;
                    }

                    $teacher = collect($teachersBySubject[$subjectId])->sortBy(fn (User $teacher): int => $load[$teacher->id] ?? 0)->first();
                    $load[$teacher->id] = ($load[$teacher->id] ?? 0) + 1;
                    $assignments[] = ['section_id' => $section->id, 'subject_id' => $subjectId, 'teacher_id' => $teacher->id];
                }
            }
        }

        SectionSubjectTeacher::query()->fillAndInsert($assignments);
    }

    /**
     * A school day of eight periods with a short break and lunch.
     */
    private function bellSchedule(AcademicYear $year): void
    {
        foreach (self::PERIODS as $order => [$name, $startsAt, $endsAt]) {
            $year->periods()->firstOrCreate(['name' => $name], [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_break' => ! str_starts_with($name, 'Period'),
                'sort_order' => $order + 1,
            ]);
        }
    }

    /**
     * Creates the year's classes and sections, offers each class its
     * subjects and gives each section its class teacher.
     *
     * @param  list<User>  $teachers
     * @param  array<int, list<int>>  $subjectIds
     * @return array<int, array<string, Section>> sections by class number, then section name
     */
    private function classes(AcademicYear $year, array $teachers, array $subjectIds): array
    {
        $sections = [];

        foreach (range(1, self::HIGHEST_CLASS) as $grade) {
            $class = $year->classes()->firstOrCreate(['name' => "Class {$grade}"], ['sort_order' => $grade]);
            $class->subjects()->syncWithoutDetaching($subjectIds[$grade]);

            foreach (self::SECTIONS as $index => $name) {
                $section = $class->sections()->firstOrCreate(['name' => $name], ['capacity' => self::SECTION_CAPACITY]);
                // A class teacher changed since the last run is kept.
                $section->class_teacher_id ??= $teachers[($grade - 1) * count(self::SECTIONS) + $index]->id;
                $section->save();

                $sections[$grade][$name] = $section;
            }
        }

        return $sections;
    }

    /**
     * Brings every class of the year up to 50 students. Last year's
     * students of the class below move up into the same section, then new
     * students fill the emptiest sections. Last year's top class leaves.
     *
     * @param  array<int, array<string, Section>>  $sections
     * @param  array<int, array<string, Section>>  $previousSections  last year's, empty for the first year
     */
    private function fillClasses(AcademicYear $year, array $sections, array $previousSections): void
    {
        foreach ($sections as $grade => $classSections) {
            /** @var array<string, array<int, string>> $joining student names by id, per section */
            $joining = [];
            $seated = [];
            $promotedEnrollmentIds = [];

            foreach ($classSections as $name => $section) {
                $previousSection = $previousSections[$grade - 1][$name] ?? null;
                $movingUp = $previousSection === null
                    ? collect()
                    : $previousSection->enrollments()->with('student:id,name')->where('status', EnrollmentStatus::Active)->get();

                $joining[$name] = $movingUp->pluck('student.name', 'student_id')->all();
                $seated[$name] = $section->enrollments()->count() + $movingUp->count();
                $promotedEnrollmentIds = [...$promotedEnrollmentIds, ...$movingUp->pluck('id')->all()];
            }

            foreach ($this->admitStudents(self::STUDENTS_PER_CLASS - array_sum($seated), $grade, $year) as $studentId => $studentName) {
                $name = array_search(min($seated), $seated, true);
                $joining[$name][$studentId] = $studentName;
                $seated[$name]++;
            }

            $this->enroll($year, $classSections, $joining);
            StudentEnrollment::query()->whereKey($promotedEnrollmentIds)->update(['status' => EnrollmentStatus::Promoted]);
        }

        foreach ($previousSections[self::HIGHEST_CLASS] ?? [] as $section) {
            $section->enrollments()->where('status', EnrollmentStatus::Active)->update(['status' => EnrollmentStatus::Left]);
        }
    }

    /**
     * Adds new students of the right age for the class, each with a father
     * and a mother as guardians.
     *
     * @return array<int, string> names by student id
     */
    private function admitStudents(int $count, int $grade, AcademicYear $year): array
    {
        if ($count < 1) {
            return [];
        }

        // Aged 6 in Class 1, 7 in Class 2 and so on when the year starts.
        $bornFrom = $year->starts_on->copy()->subYears($grade + 6)->addDay();
        $bornUntil = $year->starts_on->copy()->subYears($grade + 5);
        $students = [];
        $guardians = [];

        for ($added = 0; $added < $count; $added++) {
            $gender = $this->faker->randomElement([Gender::Male, Gender::Female]);
            $familyName = $this->faker->lastName();
            $admissionNo = $this->nextAdmissionNo();

            $students[$admissionNo] = [
                'admission_no' => $admissionNo,
                'name' => $this->faker->firstName($gender->value).' '.$familyName,
                'date_of_birth' => $this->faker->dateTimeBetween($bornFrom, $bornUntil)->format('Y-m-d'),
                'gender' => $gender,
            ];

            foreach ([GuardianRelation::Father, GuardianRelation::Mother] as $relation) {
                $phone = $this->faker->unique()->numerify('9#########');
                $isFather = $relation === GuardianRelation::Father;

                $guardians[] = [
                    'admission_no' => $admissionNo,
                    'relation' => $relation,
                    'is_primary' => $isFather,
                    'name' => ($isFather ? $this->faker->firstNameMale() : $this->faker->firstNameFemale()).' '.$familyName,
                    'phone' => $phone,
                    // Bulk inserts skip Guardian's saving hook, which sets this.
                    'phone_hash' => Guardian::hashPhone($phone),
                    'email' => $isFather ? $this->faker->unique()->safeEmail() : null,
                ];
            }
        }

        Student::query()->fillAndInsert(array_values($students));
        Guardian::query()->fillAndInsert(array_map(
            fn (array $guardian): array => array_diff_key($guardian, array_flip(['admission_no', 'relation', 'is_primary'])),
            $guardians,
        ));

        $studentIds = Student::query()->whereIn('admission_no', array_keys($students))->pluck('id', 'admission_no');
        $guardianIds = Guardian::query()->whereIn('phone_hash', array_column($guardians, 'phone_hash'))->pluck('id', 'phone_hash');
        $now = now();

        DB::table('guardian_student')->insert(array_map(fn (array $guardian): array => [
            'guardian_id' => $guardianIds[$guardian['phone_hash']],
            'student_id' => $studentIds[$guardian['admission_no']],
            'relation' => $guardian['relation']->value,
            'is_primary' => $guardian['is_primary'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $guardians));

        return $studentIds->mapWithKeys(fn (int $id, string $admissionNo): array => [$id => $students[$admissionNo]['name']])->all();
    }

    /**
     * Enrolls each section's joining students, with roll numbers in
     * alphabetical order after those already taken, as on a printed register.
     *
     * @param  array<string, Section>  $classSections
     * @param  array<string, array<int, string>>  $joining  student names by id, per section
     */
    private function enroll(AcademicYear $year, array $classSections, array $joining): void
    {
        $enrollments = [];

        foreach ($joining as $name => $students) {
            $rollNo = (int) $classSections[$name]->enrollments()->max('roll_no');
            asort($students);

            foreach (array_keys($students) as $studentId) {
                $enrollments[] = [
                    'student_id' => $studentId,
                    'academic_year_id' => $year->id,
                    'section_id' => $classSections[$name]->id,
                    'roll_no' => ++$rollNo,
                    'status' => EnrollmentStatus::Active,
                ];
            }
        }

        StudentEnrollment::query()->fillAndInsert($enrollments);
    }

    /**
     * The next number in AdmitApplicant's series, e.g. "ABC00042", so
     * students admitted later carry on after the sample ones.
     */
    private function nextAdmissionNo(): string
    {
        do {
            $admissionNo = strtoupper($this->organization->code()).str_pad((string) $this->sequences->next('student.admission_no'), 5, '0', STR_PAD_LEFT);
        } while (Student::query()->where('admission_no', $admissionNo)->exists());

        return $admissionNo;
    }
}
