<?php

namespace App\Domain\Student\Actions;

use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a guardian to a student. A guardian with the same phone number is
 * reused, so siblings share one guardian record. Each student has exactly
 * one primary guardian.
 */
class LinkGuardian
{
    /**
     * @param  array{name: string, phone: string, email?: string|null}  $details
     */
    public function handle(Student $student, array $details, GuardianRelation $relation, bool $isPrimary = false): Guardian
    {
        return DB::transaction(function () use ($student, $details, $relation, $isPrimary): Guardian {
            $guardian = Guardian::query()->where('phone_hash', Guardian::hashPhone($details['phone']))->first()
                ?? Guardian::query()->create([
                    'name' => $details['name'],
                    'phone' => Guardian::normalisePhone($details['phone']),
                    'email' => $details['email'] ?? null,
                ]);

            if ($student->guardians()->whereKey($guardian->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'This guardian is already linked to the student.',
                ]);
            }

            $isPrimary = $isPrimary || ! $student->guardians()->exists();

            if ($isPrimary) {
                $student->guardians()->newPivotQuery()->update(['is_primary' => false]);
            }

            $student->guardians()->attach($guardian->id, ['relation' => $relation->value, 'is_primary' => $isPrimary]);

            return $student->guardians()->whereKey($guardian->id)->firstOrFail();
        });
    }
}
