<?php

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetTimetableSlotRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
