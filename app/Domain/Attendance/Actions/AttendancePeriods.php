<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Academic\Models\Period;
use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\TimetableEntry;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Support\OrganizationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The periods an attendance sheet can be taken for, by the client's
 * attendance mode (attendance.mode):
 *
 * - daily: one sheet for the day, stored as period 0.
 * - session: one sheet per session (morning or afternoon), stored against
 *   the session's first teaching period by sort order.
 * - period: one sheet per teaching period that the section has on the
 *   timetable that weekday, stored against that period.
 */
final class AttendancePeriods
{
    public function __construct(private OrganizationContext $organization) {}

    public function mode(): string
    {
        return (string) $this->organization->settingOrDefault('attendance.mode');
    }

    /**
     * The sheets a day has in this mode, in order. Empty in daily mode.
     *
     * @return list<array{id: int, label: string, starts_at: string, ends_at: string}>
     */
    public function options(Section $section, CarbonInterface $date): array
    {
        return match ($this->mode()) {
            'session' => $this->sessionStarts($section),
            'period' => $this->timetablePeriods($section, $date),
            default => [],
        };
    }

    /**
     * The period a sheet is stored under for a request: 0 for the day in
     * daily mode, else the chosen period, which must be one of the options.
     *
     * @throws ValidationException
     */
    public function resolve(Section $section, CarbonInterface $date, ?int $periodId): int
    {
        $mode = $this->mode();

        if ($mode === 'daily') {
            if ($periodId !== null) {
                throw ValidationException::withMessages(['period_id' => 'Attendance is taken for the whole day in this school, so no period is chosen.']);
            }

            return AttendanceRecord::DAY;
        }

        if ($periodId === null) {
            throw ValidationException::withMessages(['period_id' => $mode === 'session' ? 'Choose a session.' : 'Choose a period.']);
        }

        if (! collect($this->options($section, $date))->contains('id', $periodId)) {
            throw ValidationException::withMessages(['period_id' => $mode === 'session'
                ? 'That is not the first period of a session.'
                : 'This section has no such period on this day in the timetable.']);
        }

        return $periodId;
    }

    /**
     * @return list<array{id: int, label: string, starts_at: string, ends_at: string}>
     */
    private function sessionStarts(Section $section): array
    {
        return $this->teachingPeriods($section)
            ->whereNotNull('session')
            ->unique('session')
            ->values()
            ->map(fn (Period $period): array => $this->option($period, ucfirst((string) $period->session).' session'))
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, starts_at: string, ends_at: string}>
     */
    private function timetablePeriods(Section $section, CarbonInterface $date): array
    {
        $periodIds = TimetableEntry::query()
            ->where('section_id', $section->id)
            ->where('weekday', $date->dayOfWeekIso)
            ->pluck('period_id');

        return $this->teachingPeriods($section)
            ->whereIn('id', $periodIds)
            ->map(fn (Period $period): array => $this->option($period, $period->name))
            ->values()
            ->all();
    }

    /**
     * The teaching periods of the section's year in bell-schedule order.
     *
     * @return Collection<int, Period>
     */
    private function teachingPeriods(Section $section): Collection
    {
        $yearId = $section->loadMissing('schoolClass')->schoolClass->academic_year_id;

        return Period::query()
            ->where('academic_year_id', $yearId)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array{id: int, label: string, starts_at: string, ends_at: string}
     */
    private function option(Period $period, string $label): array
    {
        return [
            'id' => $period->id,
            'label' => $label,
            'starts_at' => substr($period->starts_at, 0, 5),
            'ends_at' => substr($period->ends_at, 0, 5),
        ];
    }
}
