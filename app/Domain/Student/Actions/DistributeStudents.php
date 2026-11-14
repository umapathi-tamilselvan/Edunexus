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
 * Places the students of a class into its sections for the class's year.
 *
 * A class's students are the students enrolled last year in a class of the
 * same name who have no enrollment this year. Each one is enrolled through
 * EnrollStudent, so roll numbers and capacity locks apply. Sections fill in
 * name order, each up to its capacity; students with no seat left stay
 * unplaced and are counted.
 */
class DistributeStudents
{
    public function __construct(private EnrollStudent $enrollStudent) {}

    /**
     * @return array{placed: int, left_over: int, sections: list<array{id: int, name: string, capacity: int|null, placed: int}>}
     */
    public function handle(SchoolClass $schoolClass, string $order): array
    {
        $schoolClass->loadMissing('academicYear');
        $year = $schoolClass->academicYear;

        $sections = $schoolClass->sections()->orderBy('name')->get();
        $pool = $this->pool($schoolClass, $year, $order);
        $seatsLeft = $this->seatsLeft($sections, $year);
        $placed = array_fill_keys($sections->modelKeys(), 0);

        DB::transaction(function () use ($pool, $sections, &$seatsLeft, &$placed): void {
            foreach ($pool as $student) {
                $section = $sections->first(fn (Section $candidate): bool => $seatsLeft[$candidate->id] > 0);

                if ($section === null) {
                    break;
                }

                $this->enrollStudent->handle($student, $section);
                $seatsLeft[$section->id]--;
                $placed[$section->id]++;
            }
        });

        return [
            'placed' => array_sum($placed),
            'left_over' => $pool->count() - array_sum($placed),
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'placed' => $placed[$section->id],
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    private function pool(SchoolClass $schoolClass, AcademicYear $year, string $order): Collection
    {
        $previousYear = AcademicYear::query()
            ->where('starts_on', '<', $year->starts_on)
            ->orderByDesc('starts_on')
            ->first();

        if ($previousYear === null) {
            return collect();
        }

        $sameClassLastYear = SchoolClass::query()
            ->select('id')
            ->where('academic_year_id', $previousYear->id)
            ->where('name', $schoolClass->name);

        return Student::query()
            ->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('academic_year_id', $previousYear->id)
                ->where('status', '!=', EnrollmentStatus::Left)
                ->whereHas('section', fn (Builder $section) => $section->whereIn('class_id', $sameClassLastYear)))
            ->whereDoesntHave('enrollments', fn (Builder $enrollments) => $enrollments->where('academic_year_id', $year->id))
            ->orderBy($order === 'admission_no' ? 'admission_no' : 'name')
            ->get();
    }

    /**
     * Seats free in each section this year, keyed by section id; a section
     * with no capacity has unlimited seats.
     *
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
