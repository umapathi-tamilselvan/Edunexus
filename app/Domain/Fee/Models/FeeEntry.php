<?php

namespace App\Domain\Fee\Models;

use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of a student's fee ledger for an enrollment, in paise: dues are
 * positive, payments negative, and a reversal is the opposite of the entry
 * it cancels, so the balance is the sum of the amounts.
 *
 * Entries are never changed or deleted; a mistake is reversed. Each one
 * records who posted it and when, so the ledger is its own audit trail.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property FeeEntryType $type
 * @property int|null $fee_head_id
 * @property int $amount
 * @property Carbon|null $due_on
 * @property string|null $description
 * @property int|null $receipt_id
 * @property int|null $reverses_id
 * @property int|null $recorded_by
 * @property Carbon $created_at
 */
#[Fillable(['enrollment_id', 'type', 'fee_head_id', 'amount', 'due_on', 'description', 'receipt_id', 'reverses_id', 'recorded_by'])]
class FeeEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fee entries are never changed. Post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Fee entries are never deleted. Post a reversal instead.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeeEntryType::class,
            'amount' => 'integer',
            'due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    /**
     * @return BelongsTo<FeeHead, $this>
     */
    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    /**
     * @return BelongsTo<FeeReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }

    /**
     * The entry this one cancels.
     *
     * @return BelongsTo<FeeEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * The entry that cancels this one, if any.
     *
     * @return HasOne<FeeEntry, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
