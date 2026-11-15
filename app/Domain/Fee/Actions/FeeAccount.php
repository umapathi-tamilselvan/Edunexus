<?php

namespace App\Domain\Fee\Actions;

use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Student\Models\StudentEnrollment;

/**
 * An enrollment's fee account, worked out from its ledger alone: the
 * balance (positive is owed, negative is paid in advance), each due with
 * what has been paid towards it and what is outstanding, payments going to
 * the oldest dues first, and every entry, newest first.
 */
class FeeAccount
{
    /**
     * @return array{balance: int, total_due: int, total_paid: int, advance: int, overdue: int, dues: list<array<string, mixed>>, entries: list<array<string, mixed>>}
     */
    public function handle(StudentEnrollment $enrollment): array
    {
        $entries = $enrollment->feeEntries()
            ->with(['feeHead:id,name', 'receipt:id,receipt_no,method,reference,paid_on', 'reversal:id,reverses_id,description,created_at', 'recordedBy:id,name'])
            ->orderBy('id')
            ->get();

        $standing = $entries->filter(fn (FeeEntry $entry): bool => $entry->reversal === null && $entry->type !== FeeEntryType::Reversal);
        $paid = -$standing->where('type', FeeEntryType::Payment)->sum('amount');
        $unapplied = $paid;

        $dues = $standing
            ->where('type', FeeEntryType::Due)
            ->sortBy([['due_on', 'asc'], ['id', 'asc']])
            ->map(function (FeeEntry $due) use (&$unapplied): array {
                $applied = min($due->amount, $unapplied);
                $unapplied -= $applied;
                $outstanding = $due->amount - $applied;

                return [
                    'id' => $due->id,
                    'fee_head' => $due->feeHead?->name,
                    'description' => $due->description,
                    'due_on' => $due->due_on->toDateString(),
                    'amount' => $due->amount,
                    'paid' => $applied,
                    'outstanding' => $outstanding,
                    'is_overdue' => $outstanding > 0 && $due->due_on->lt(today()),
                ];
            })
            ->values();

        return [
            'balance' => (int) $entries->sum('amount'),
            'total_due' => (int) $dues->sum('amount'),
            'total_paid' => (int) $paid,
            'advance' => (int) $unapplied,
            'overdue' => (int) $dues->where('is_overdue', true)->sum('outstanding'),
            'dues' => $dues->all(),
            'entries' => $entries->sortByDesc('id')->map(fn (FeeEntry $entry): array => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'amount' => $entry->amount,
                'fee_head' => $entry->feeHead?->name,
                'description' => $entry->description,
                'due_on' => $entry->due_on?->toDateString(),
                'receipt' => $entry->receipt === null ? null : [
                    'id' => $entry->receipt->id,
                    'receipt_no' => $entry->receipt->receipt_no,
                    'method' => $entry->receipt->method->value,
                    'reference' => $entry->receipt->reference,
                    'paid_on' => $entry->receipt->paid_on->toDateString(),
                ],
                'reverses_id' => $entry->reverses_id,
                'reversed' => $entry->reversal === null ? null : ['id' => $entry->reversal->id, 'reason' => $entry->reversal->description, 'at' => $entry->reversal->created_at->toIso8601String()],
                'recorded_by' => $entry->recordedBy?->name,
                'created_at' => $entry->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
