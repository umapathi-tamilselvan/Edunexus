<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Models\AttendanceRecord;
use Illuminate\Support\Collection;

/**
 * Attendance as a measure for scores: units attended over units marked, the
 * way SummarizeAttendance counts them (present and late 1, half day 0.5,
 * absent 0, leave left out). One grouped query, however many enrollments.
 */
final class AttendanceScores
{
    /**
     * The measure of each enrollment, keyed by enrollment id.
     *
     * @param  list<int>  $enrollmentIds
     * @return Collection<int, array{days_marked: int, days_attended: float, percentage: float|null}>
     */
    public function perEnrollment(array $enrollmentIds): Collection
    {
        $marked = [];
        $attended = [];

        $counts = AttendanceRecord::query()
            ->toBase()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->groupBy('enrollment_id', 'status')
            ->selectRaw('enrollment_id, status, COUNT(*) AS total')
            ->get();

        foreach ($counts as $row) {
            $status = AttendanceStatus::from($row->status);

            if (! $status->countsInPercentage()) {
                continue;
            }

            $marked[$row->enrollment_id] = ($marked[$row->enrollment_id] ?? 0) + (int) $row->total;
            $attended[$row->enrollment_id] = ($attended[$row->enrollment_id] ?? 0.0) + (int) $row->total * $status->attended();
        }

        return collect($enrollmentIds)->mapWithKeys(function (int $enrollmentId) use ($marked, $attended): array {
            $days = $marked[$enrollmentId] ?? 0;
            $units = $attended[$enrollmentId] ?? 0.0;

            return [$enrollmentId => [
                'days_marked' => $days,
                'days_attended' => $units,
                'percentage' => $days === 0 ? null : round($units / $days * 100, 1),
            ]];
        });
    }

    /**
     * One measure across several enrollments: the units of all of them.
     *
     * @param  list<int>  $enrollmentIds
     * @return array{days_marked: int, days_attended: float, percentage: float|null}
     */
    public function combined(array $enrollmentIds): array
    {
        $days = 0;
        $units = 0.0;

        foreach ($this->perEnrollment($enrollmentIds) as $measure) {
            $days += $measure['days_marked'];
            $units += $measure['days_attended'];
        }

        return [
            'days_marked' => $days,
            'days_attended' => $units,
            'percentage' => $days === 0 ? null : round($units / $days * 100, 1),
        ];
    }
}
