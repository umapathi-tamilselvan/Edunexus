<?php

namespace App\Http\Requests\Api\V1\Examination;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExamMarksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'entries' => ['present', 'array', 'max:500'],
            'entries.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'entries.*.marks' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'entries.*.is_absent' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.*.marks.numeric' => 'Enter marks as a number.',
            'entries.*.marks.decimal' => 'Use at most two decimal places.',
            'entries.*.marks.min' => 'Marks cannot be negative.',
        ];
    }
}
