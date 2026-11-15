<?php

namespace App\Http\Requests\Api\V1\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class RequestGuardianOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
