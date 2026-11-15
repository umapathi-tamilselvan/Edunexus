<?php

namespace App\Domain\Student\Actions;

use App\Domain\Academic\Models\Section;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an enrollment to another section of the same academic year and/or
 * changes its roll number. Moving between years is promotion, not this.
 */
class UpdateEnrollment
{
    public function handle(StudentEnrollment $enrollment, ?Section $section, ?int $rollNo, bool $rollNoGiven): StudentEnrollment
    {
        return DB::transaction(function () use ($enrollment, $section, $rollNo, $rollNoGiven): StudentEnrollment {
            if ($section === null && $enrollment->section_id === null) {
                throw ValidationException::withMessages(['section_id' => 'Choose a section to place this student in.']);
            }

            $moving = $section !== null && $section->id !== $enrollment->section_id;
            $target = SectionSeats::lock($section ?? $enrollment->section);

            if ($moving) {
                if ($target->schoolClass->academic_year_id !== $enrollment->academic_year_id) {
                    throw ValidationException::withMessages([
                        'section_id' => 'An enrollment can only move to a section in the same academic year. Use promotion to change years.',
                    ]);
                }

                SectionSeats::ensureAvailable($target);
            }

            if ($moving || $rollNoGiven) {
                $enrollment->roll_no = $rollNoGiven && $rollNo === null
                    ? null
                    : SectionSeats::rollNumber($target, $rollNoGiven ? $rollNo : null, ignoreEnrollmentId: $enrollment->id);
            }

            $enrollment->section_id = $target->id;
            $enrollment->class_id = $target->class_id;
            $enrollment->save();

            return $enrollment->load(['academicYear', 'schoolClass', 'section.schoolClass']);
        });
    }
}
