<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Student\Models\StudentEnrollment;

/**
 * A student's attendance in one enrollment. A unit is one record: a day, a
 * session or a period, depending on the attendance mode. The summary gives
 * the units marked, the units attended (late counts in full, half a day as
 * half) and the percentage, with the most recent units they were not fully
 * present for. Units with no record never count, because no attendance is
 * taken on them. Leave is excused, so it is left out of both the attended
 * units and the marked units.
 */
class SummarizeAttendance
{
    /**
     * @return array{days_marked: int, days_attended: float, percentage: float|null, by_status: array<string, int>, missed: list<array{date: string, status: string, remark: string|null, period_id: int|null}>}
     */
    public function handle(StudentEnrollment $enrollment): array
    {
        $byStatus = $enrollment->attendance()
            ->toBase()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS days')
            ->pluck('days', 'status');

        $counts = [];
        $attended = 0.0;
        $marked = 0;

        foreach (AttendanceStatus::cases() as $status) {
            $counts[$status->value] = (int) $byStatus->get($status->value, 0);

            if ($status->countsInPercentage()) {
                $marked += $counts[$status->value];
                $attended += $counts[$status->value] * $status->attended();
            }
        }

        return [
            'days_marked' => $marked,
            'days_attended' => $attended,
            'percentage' => $marked === 0 ? null : round($attended / $marked * 100, 1),
            'by_status' => $counts,
            'missed' => $enrollment->attendance()
                ->whereNotIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])
                ->orderByDesc('date')
                ->orderByDesc('period')
                ->limit(30)
                ->get(['date', 'period', 'status', 'remark'])
                ->map(fn (AttendanceRecord $record): array => [
                    'date' => $record->date->toDateString(),
                    'status' => $record->status->value,
                    'remark' => $record->remark,
                    'period_id' => $record->period === AttendanceRecord::DAY ? null : $record->period,
                ])
                ->all(),
        ];
    }
}
