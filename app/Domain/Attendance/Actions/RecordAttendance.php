<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Identity\Models\User;
use App\Support\BulkAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a section's attendance sheet for one day: one SELECT of what is
 * already marked, then one upsert and at most one delete, however many
 * students the section has.
 *
 * Rules: the day is a school day of the section's academic year (in the
 * year, not in the future, not a holiday), and every student is enrolled
 * in the section. A student without a status has their record cleared.
 * Changing or clearing a record already marked is audited.
 */
class RecordAttendance
{
    /**
     * @param  list<array{enrollment_id: int, status: string|null, remark?: string|null}>  $entries
     * @return array{marked: int, cleared: int}
     */
    public function handle(Section $section, CarbonInterface $date, array $entries, User $markedBy): array
    {
        if (($closed = self::closedReason($section, $date)) !== null) {
            throw ValidationException::withMessages(['date' => $closed]);
        }

        $enrolled = $section->enrollments()->pluck('id')->all();
        $errors = [];

        foreach ($entries as $index => $entry) {
            if (! in_array((int) $entry['enrollment_id'], $enrolled, true)) {
                $errors["entries.{$index}.enrollment_id"] = "This student is not in {$section->schoolClass->name} {$section->name}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($date, $entries, $markedBy): array {
            $day = $date->toDateString();
            $marked = AttendanceRecord::query()
                ->whereIn('enrollment_id', array_map(fn (array $entry): int => (int) $entry['enrollment_id'], $entries))
                ->where('date', $day)
                ->where('period', AttendanceRecord::DAY)
                ->lockForUpdate()
                ->get()
                ->keyBy('enrollment_id');

            $rows = [];
            $cleared = [];
            $changes = [];

            foreach ($entries as $entry) {
                $record = $marked->get((int) $entry['enrollment_id']);
                $status = $entry['status'] === null ? null : AttendanceStatus::from($entry['status']);
                $remark = $status === null ? null : (trim((string) ($entry['remark'] ?? '')) ?: null);

                if ($status === null) {
                    if ($record !== null) {
                        $cleared[] = $record->id;
                        $changes[] = ['id' => $record->id, 'event' => 'deleted', 'old' => self::values($record->status, $record->remark), 'new' => []];
                    }

                    continue;
                }

                if ($record !== null && $record->status === $status && $record->remark === $remark) {
                    continue;
                }

                if ($record !== null) {
                    $changes[] = ['id' => $record->id, 'event' => 'updated', 'old' => self::values($record->status, $record->remark), 'new' => self::values($status, $remark)];
                }

                $rows[] = [
                    'enrollment_id' => (int) $entry['enrollment_id'],
                    'date' => $day,
                    'period' => AttendanceRecord::DAY,
                    'status' => $status->value,
                    'remark' => $remark,
                    'marked_by' => $markedBy->id,
                ];
            }

            if ($rows !== []) {
                AttendanceRecord::query()->upsert($rows, ['enrollment_id', 'date', 'period'], ['status', 'remark', 'marked_by']);
            }

            if ($cleared !== []) {
                AttendanceRecord::query()->whereKey($cleared)->delete();
            }

            // Bulk writes fire no model events; edits are audited here instead.
            BulkAudit::record(AttendanceRecord::class, $changes);

            return ['marked' => count($rows), 'cleared' => count($cleared)];
        });
    }

    /**
     * Why no attendance can be taken on the day, or null on a school day.
     */
    public static function closedReason(Section $section, CarbonInterface $date): ?string
    {
        $year = $section->loadMissing('schoolClass.academicYear')->schoolClass->academicYear;

        if (! $year->includes($date)) {
            return "{$date->format('j M Y')} is not in {$year->name}.";
        }

        if ($date->gt(today())) {
            return 'Attendance cannot be taken for a day still to come.';
        }

        $holiday = $year->holidays()->where('date', $date->toDateString())->value('name');

        return $holiday === null ? null : "{$date->format('j M Y')} is a holiday ({$holiday}).";
    }

    /**
     * @return array{status: string, remark: string|null}
     */
    private static function values(AttendanceStatus $status, ?string $remark): array
    {
        return ['status' => $status->value, 'remark' => $remark];
    }
}
