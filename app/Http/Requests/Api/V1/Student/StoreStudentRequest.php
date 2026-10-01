<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domain\Student\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_no' => ['required', 'string', 'max:30', Rule::unique('students', 'admission_no')],
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'guardians' => ['required', 'array', 'min:1', 'max:4'],
            ...GuardianRules::for('guardians.*.'),
            'enrollment' => ['nullable', 'array:section_id,roll_no'],
            'enrollment.section_id' => ['required_with:enrollment', 'integer', Rule::exists('sections', 'id')],
            'enrollment.roll_no' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guardians.*.name' => 'guardian name',
            'guardians.*.phone' => 'guardian phone',
            'guardians.*.email' => 'guardian email',
            'guardians.*.relation' => 'guardian relation',
        ];
    }
}
