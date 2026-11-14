<?php

namespace App\Domain\Fee\Models;

use App\Domain\Fee\Enums\PaymentMethod;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The receipt for one payment, e.g. "ABC/2026-27/00042": numbered without
 * gaps per financial year (April to March) and prefixed with the client's
 * RECEIPT_PREFIX. A reversed payment keeps its receipt.
 *
 * @property int $id
 * @property string $receipt_no
 * @property string $financial_year
 * @property int $enrollment_id
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property Carbon $paid_on
 * @property string|null $idempotency_key
 * @property string|null $gateway_payment_id
 * @property int|null $received_by
 */
#[Fillable(['receipt_no', 'financial_year', 'enrollment_id', 'amount', 'method', 'reference', 'paid_on', 'idempotency_key', 'gateway_payment_id', 'received_by'])]
class FeeReceipt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
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
     * The payment entry this receipt was issued for.
     *
     * @return HasOne<FeeEntry, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(FeeEntry::class, 'receipt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * The financial year (April to March) a date falls in, e.g. "2026-27".
     */
    public static function financialYearOf(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }
}
