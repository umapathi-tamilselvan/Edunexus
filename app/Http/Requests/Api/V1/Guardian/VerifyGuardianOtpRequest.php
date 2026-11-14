<?php

namespace App\Http\Requests\Api\V1\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class VerifyGuardianOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
