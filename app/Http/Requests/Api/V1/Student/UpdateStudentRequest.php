<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'admission_no' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('students', 'admission_no')->ignore($student)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
        ];
    }
}
