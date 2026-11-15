<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings a class's students into the year and places them in its sections (D45).
 *
 * 1. Moving up: students enrolled last year in the class just below this one
 *    (the previous class by sort order), and not yet enrolled this year, are
 *    enrolled in this class without a section.
 * 2. Placing: every enrolment of this class with no section is placed in the
 *    class's sections in name order, each up to its capacity. Students are
 *    ordered by name or admission number. A placement takes a section lock and
 *    the normal roll-number rules; students with no seat left stay unplaced.
 */
class DistributeStudents
{
    public function __construct(private EnrollStudentToClass $enrollStudentToClass) {}

    /**
     * @return array{promoted: int, placed: int, left_over: int, sections: list<array{id: int, name: string, capacity: int|null, placed: int}>}
     */
    public function handle(SchoolClass $schoolClass, string $order): array
    {
        $schoolClass->loadMissing('academicYear');
        $year = $schoolClass->academicYear;

        return DB::transaction(function () use ($schoolClass, $year, $order): array {
            $promoted = $this->moveUp($schoolClass, $year);

            return ['promoted' => $promoted, ...$this->place($schoolClass, $year, $order)];
        });
    }

    /**
     * Enrols last year's students of the previous class in this class.
     */
    private function moveUp(SchoolClass $schoolClass, AcademicYear $year): int
    {
        $previousYear = AcademicYear::query()
            ->where('starts_on', '<', $year->starts_on)
            ->orderByDesc('starts_on')
            ->first();

        $previousClass = $previousYear === null ? null : SchoolClass::query()
            ->where('academic_year_id', $previousYear->id)
            ->where('sort_order', '<', $schoolClass->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previousClass === null) {
            return 0;
        }

        $students = Student::query()
            ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('academic_year_id', $previousYear->id)
                ->where('class_id', $previousClass->id)
                ->where('status', '!=', EnrollmentStatus::Left))
            ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments->where('academic_year_id', $year->id))
            ->orderBy('name')
            ->get();

        foreach ($students as $student) {
            $this->enrollStudentToClass->handle($student, $schoolClass);
        }

        return $students->count();
    }

    /**
     * Places this class's unplaced enrolments into its sections.
     *
     * @return array{placed: int, left_over: int, sections: list<array{id: int, name: string, capacity: int|null, placed: int}>}
     */
    private function place(SchoolClass $schoolClass, AcademicYear $year, string $order): array
    {
        $sections = $schoolClass->sections()->orderBy('name')->get();
        $waiting = StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->where('student_enrollments.academic_year_id', $year->id)
            ->where('student_enrollments.class_id', $schoolClass->id)
            ->whereNull('student_enrollments.section_id')
            ->orderBy($order === 'admission_no' ? 'students.admission_no' : 'students.name')
            ->select('student_enrollments.*')
            ->get();

        $seatsLeft = $this->seatsLeft($sections, $year);
        $placed = array_fill_keys($sections->modelKeys(), 0);

        foreach ($waiting as $enrollment) {
            $candidate = $sections->first(fn (Section $section): bool => $seatsLeft[$section->id] > 0);

            if ($candidate === null) {
                break;
            }

            $section = SectionSeats::lock($candidate);
            SectionSeats::ensureAvailable($section);

            $enrollment->update([
                'section_id' => $section->id,
                'roll_no' => SectionSeats::rollNumber($section, null),
            ]);

            $seatsLeft[$section->id]--;
            $placed[$section->id]++;
        }

        return [
            'placed' => array_sum($placed),
            'left_over' => $waiting->count() - array_sum($placed),
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'placed' => $placed[$section->id],
            ])->values()->all(),
        ];
    }

    /**
     * Seats free in each section this year, keyed by section id; a section with
     * no capacity has unlimited seats.
     *
     * @param  Collection<int, Section>  $sections
     * @return array<int, int|float>
     */
    private function seatsLeft(Collection $sections, AcademicYear $year): array
    {
        $taken = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereIn('section_id', $sections->modelKeys())
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        return $sections->mapWithKeys(fn (Section $section): array => [
            $section->id => $section->capacity === null ? INF : max(0, $section->capacity - (int) ($taken[$section->id] ?? 0)),
        ])->all();
    }
}
