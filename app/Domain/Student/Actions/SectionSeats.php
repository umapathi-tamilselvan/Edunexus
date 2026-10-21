<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Validation\ValidationException;

/**
 * Capacity and roll-number rules for a section. Callers run inside a
 * transaction after lock(), so concurrent enrollments cannot overfill a
 * section or reuse a roll number.
 */
final class SectionSeats
{
    public static function lock(Section $section): Section
    {
        return Section::query()->with('schoolClass')->lockForUpdate()->findOrFail($section->id);
    }

    public static function ensureAvailable(Section $section): void
    {
        if ($section->capacity === null) {
            return;
        }

        $taken = StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('status', EnrollmentStatus::Active)
            ->count();

        if ($taken >= $section->capacity) {
            throw ValidationException::withMessages([
                'section_id' => "Section {$section->schoolClass->name} {$section->name} is full ({$section->capacity} students).",
            ]);
        }
    }

    /**
     * The requested roll number if it is free, or the next free one.
     */
    public static function rollNumber(Section $section, ?int $requested, ?int $ignoreEnrollmentId = null): int
    {
        $enrollments = StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->when($ignoreEnrollmentId, fn ($query) => $query->whereKeyNot($ignoreEnrollmentId));

        if ($requested === null) {
            return ((int) $enrollments->max('roll_no')) + 1;
        }

        if ((clone $enrollments)->where('roll_no', $requested)->exists()) {
            throw ValidationException::withMessages([
                'roll_no' => "Roll number {$requested} is already taken in this section.",
            ]);
        }

        return $requested;
    }
}
