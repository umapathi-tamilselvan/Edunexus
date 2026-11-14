<?php

namespace App\Domain\Fee\Actions;

use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Fee\Enums\PaymentMethod;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Fee\Models\FeeReceipt;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use App\Support\Sequences;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a payment against an enrollment and issues its receipt, numbered
 * without gaps per financial year: "<RECEIPT_PREFIX>/2026-27/00042". Part
 * and advance payments are fine; FeeAccount puts payments against the
 * oldest dues first.
 *
 * Sending the same idempotency key (or gateway payment id) again returns
 * the first receipt instead of taking the money twice.
 */
class CollectPayment
{
    public function __construct(private Sequences $sequences) {}

    public function handle(
        StudentEnrollment $enrollment,
        int $amount,
        PaymentMethod $method,
        ?string $reference,
        Carbon $paidOn,
        User $receivedBy,
        ?string $idempotencyKey = null,
        ?string $gatewayPaymentId = null,
    ): FeeReceipt {
        if ($paidOn->gt(today())) {
            throw ValidationException::withMessages(['paid_on' => 'A payment cannot be dated in the future.']);
        }

        if (($earlier = $this->earlierReceipt($idempotencyKey, $gatewayPaymentId)) !== null) {
            return $this->sameAs($earlier, $enrollment, $amount);
        }

        try {
            return DB::transaction(function () use ($enrollment, $amount, $method, $reference, $paidOn, $receivedBy, $idempotencyKey, $gatewayPaymentId): FeeReceipt {
                $financialYear = FeeReceipt::financialYearOf($paidOn);
                $number = $this->sequences->next("fee.receipt.{$financialYear}");

                $receipt = FeeReceipt::query()->create([
                    'receipt_no' => sprintf('%s/%s/%05d', config('edunexus.receipt_prefix'), $financialYear, $number),
                    'financial_year' => $financialYear,
                    'enrollment_id' => $enrollment->id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'paid_on' => $paidOn,
                    'idempotency_key' => $idempotencyKey,
                    'gateway_payment_id' => $gatewayPaymentId,
                    'received_by' => $receivedBy->id,
                ]);

                FeeEntry::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'type' => FeeEntryType::Payment,
                    'amount' => -$amount,
                    'description' => "Receipt {$receipt->receipt_no}",
                    'receipt_id' => $receipt->id,
                    'recorded_by' => $receivedBy->id,
                ]);

                return $receipt;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A retry with the same key got in first; its receipt number was given back.
            $earlier = $this->earlierReceipt($idempotencyKey, $gatewayPaymentId) ?? throw $exception;

            return $this->sameAs($earlier, $enrollment, $amount);
        }
    }

    private function earlierReceipt(?string $idempotencyKey, ?string $gatewayPaymentId): ?FeeReceipt
    {
        if ($idempotencyKey === null && $gatewayPaymentId === null) {
            return null;
        }

        return FeeReceipt::query()
            ->where(fn ($receipts) => $receipts
                ->when($idempotencyKey, fn ($query) => $query->orWhere('idempotency_key', $idempotencyKey))
                ->when($gatewayPaymentId, fn ($query) => $query->orWhere('gateway_payment_id', $gatewayPaymentId)))
            ->first();
    }

    private function sameAs(FeeReceipt $earlier, StudentEnrollment $enrollment, int $amount): FeeReceipt
    {
        if ($earlier->enrollment_id !== $enrollment->id || $earlier->amount !== $amount) {
            throw ValidationException::withMessages(['idempotency_key' => "This payment was already recorded differently, as receipt {$earlier->receipt_no}."]);
        }

        return $earlier;
    }
}
