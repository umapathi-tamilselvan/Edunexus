<?php

namespace App\Domain\Student\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Student;

/**
 * What a guardian's login may see: their linked children, and nothing else.
 * Guardians hold no permissions, so this is the only rule that lets them in.
 * Called as Gate::authorize('child', [Guardian::class, $student]).
 */
class GuardianPolicy
{
    public function child(User $user, Student $student): bool
    {
        return $student->guardians()->where('user_id', $user->id)->exists();
    }
}
