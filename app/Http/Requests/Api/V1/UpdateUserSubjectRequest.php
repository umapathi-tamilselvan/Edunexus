<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSubject', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['present', 'nullable', 'integer', Rule::exists('subjects', 'id')],
        ];
    }
}
