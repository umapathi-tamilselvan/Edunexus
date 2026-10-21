<?php

namespace App\Http\Requests\Api\V1\Admission;

use Illuminate\Foundation\Http\FormRequest;

class DecideApplicationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected,withdrawn'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Give a reason when rejecting an application.',
        ];
    }
}
