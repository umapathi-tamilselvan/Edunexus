<?php

namespace App\Http\Requests\Api\V1\Fee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A due for one enrollment, every active student of a section, or every
 * active student of a class. Amounts are integer paise.
 */
class PostFeeDuesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_head_id' => ['required', 'integer', Rule::exists('fee_heads', 'id')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:255'],
            'enrollment_id' => ['required_without_all:section_id,class_id', 'prohibits:section_id,class_id', 'nullable', 'integer', Rule::exists('student_enrollments', 'id')],
            'section_id' => ['required_without_all:enrollment_id,class_id', 'prohibits:class_id', 'nullable', 'integer', Rule::exists('sections', 'id')],
            'class_id' => ['required_without_all:enrollment_id,section_id', 'nullable', 'integer', Rule::exists('classes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'A single due cannot be more than ₹10,00,000.',
        ];
    }
}
