<?php

namespace App\Domain\Fee\Actions;

use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a due or a payment with an entry of the opposite amount, e.g. a
 * due posted by mistake or a bounced cheque. Nothing is changed or
 * deleted, and a reversed payment keeps its receipt. An entry is reversed
 * at most once, and a reversal itself never is.
 */
class ReverseFeeEntry
{
    public function handle(FeeEntry $entry, string $reason, User $reversedBy): FeeEntry
    {
        if ($entry->type === FeeEntryType::Reversal) {
            throw ValidationException::withMessages(['entry' => 'A reversal cannot be reversed. Post the due or payment again instead.']);
        }

        try {
            return DB::transaction(function () use ($entry, $reason, $reversedBy): FeeEntry {
                if ($entry->reversal()->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['entry' => 'This entry has already been reversed.']);
                }

                return FeeEntry::query()->create([
                    'enrollment_id' => $entry->enrollment_id,
                    'type' => FeeEntryType::Reversal,
                    'fee_head_id' => $entry->fee_head_id,
                    'amount' => -$entry->amount,
                    'description' => $reason,
                    'reverses_id' => $entry->id,
                    'recorded_by' => $reversedBy->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['entry' => 'This entry has already been reversed.']);
        }
    }
}
