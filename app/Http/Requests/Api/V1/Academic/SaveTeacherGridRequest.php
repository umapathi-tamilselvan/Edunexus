<?php

namespace App\Http\Requests\Api\V1\Academic;

use Illuminate\Foundation\Http\FormRequest;

class SaveTeacherGridRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.*.section_id' => ['required', 'integer'],
            'sections.*.class_teacher_id' => ['nullable', 'integer'],
            'sections.*.subjects' => ['present', 'array'],
            'sections.*.subjects.*.subject_id' => ['required', 'integer'],
            'sections.*.subjects.*.teacher_id' => ['nullable', 'integer'],
        ];
    }
}
