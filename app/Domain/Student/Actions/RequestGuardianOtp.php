<?php

namespace App\Domain\Student\Actions;

use App\Domain\Communication\Jobs\SendGuardianOtp;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\GuardianOtp;

/**
 * Starts a guardian sign-in: issues a six-digit code to the phone on file.
 *
 * Nothing is revealed about whether the phone is on file: the caller gets
 * the same answer either way. A new code voids any code still open for the
 * same guardian, and the code is valid for five minutes.
 */
class RequestGuardianOtp
{
    public const LIFETIME_MINUTES = 5;

    public function handle(string $phone): void
    {
        $phoneHash = Guardian::hashPhone($phone);
        $guardian = Guardian::query()->where('phone_hash', $phoneHash)->first();

        if ($guardian === null) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        GuardianOtp::query()
            ->where('guardian_id', $guardian->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        GuardianOtp::query()->create([
            'guardian_id' => $guardian->id,
            'phone_hash' => $phoneHash,
            'code_hash' => self::codeHash($code),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            'attempts' => 0,
        ]);

        SendGuardianOtp::dispatch($guardian->id, $code);
    }

    /**
     * A keyed hash of the code, so a stored hash cannot be reversed by guessing alone.
     */
    public static function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
