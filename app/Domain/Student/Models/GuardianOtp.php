<?php

namespace App\Domain\Student\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time sign-in code for a guardian. Only the keyed hash of the code is
 * stored. Written by RequestGuardianOtp and checked by VerifyGuardianOtp.
 *
 * @property int $id
 * @property int $guardian_id
 * @property string $phone_hash
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 */
#[Fillable(['guardian_id', 'phone_hash', 'code_hash', 'expires_at', 'attempts', 'consumed_at'])]
class GuardianOtp extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
