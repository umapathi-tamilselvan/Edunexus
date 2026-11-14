<?php

namespace App\Http\Requests\Api\V1\Fee;

use App\Domain\Fee\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Amounts are integer paise. A client sends a fresh idempotency key with
 * each payment it takes, and the same key when it retries one.
 */
class CollectPaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'required_if:method,cheque,upi,bank_transfer', 'string', 'max:100'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required_if' => 'Enter the cheque number or transaction reference.',
        ];
    }
}
