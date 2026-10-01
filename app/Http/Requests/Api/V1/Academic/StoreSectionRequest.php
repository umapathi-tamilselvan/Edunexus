<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Section|null $section */
        $section = $this->route('section');
        /** @var SchoolClass|null $class */
        $class = $this->route('schoolClass');
        $classId = $section->class_id ?? $class?->id;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('sections', 'name')->where('class_id', $classId)->ignore($section)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'class_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
