<?php

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSubjectTeachersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_ids' => ['present', 'array'],
            'teacher_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }
}
