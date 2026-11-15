<?php

namespace App\Domain\Student\Actions;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\GuardianOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Checks a guardian's sign-in code and returns the guardian's login.
 *
 * A code works once, for five minutes, and only five wrong attempts are
 * allowed on it. On the first successful sign-in a login is created for the
 * guardian, with the Guardian role (which holds no permissions), and linked
 * to the guardian record. A guardian's login has no email and a password
 * nobody knows: it signs in only with codes.
 */
class VerifyGuardianOtp
{
    public const MAX_ATTEMPTS = 5;

    /**
     * @throws ValidationException
     */
    public function handle(string $phone, string $code): User
    {
        $otp = GuardianOtp::query()
            ->where('phone_hash', Guardian::hashPhone($phone))
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            throw $this->invalid();
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, RequestGuardianOtp::codeHash($code))) {
            throw $this->invalid();
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return DB::transaction(function () use ($otp): User {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($otp->guardian_id);

            if ($guardian->user_id !== null) {
                return User::query()->findOrFail($guardian->user_id);
            }

            SpatieRole::findOrCreate(Role::Guardian->value);

            $user = User::query()->create([
                'name' => $guardian->name,
                'email' => null,
                'password' => Hash::make(Str::random(64)),
            ]);
            $user->assignRole(Role::Guardian->value);

            $guardian->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'code' => 'That code is not right, or it has expired. Ask for a new code.',
        ]);
    }
}
