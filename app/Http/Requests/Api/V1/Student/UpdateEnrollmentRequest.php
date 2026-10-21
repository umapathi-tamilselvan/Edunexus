<?php

namespace App\Http\Requests\Api\V1\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['sometimes', 'required', 'integer', Rule::exists('sections', 'id')],
            'roll_no' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
