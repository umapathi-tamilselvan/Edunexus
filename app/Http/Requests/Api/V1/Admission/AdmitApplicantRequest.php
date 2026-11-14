<?php

namespace App\Http\Requests\Api\V1\Admission;

use App\Domain\Student\Enums\ConsentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmitApplicantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'admission_no' => ['nullable', 'string', 'max:30', Rule::unique('students', 'admission_no')],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:999'],
            'consent_given' => ['accepted'],
            'consent_method' => ['required', Rule::enum(ConsentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_given.accepted' => 'The guardian\'s consent to the privacy notice is required to admit a student.',
        ];
    }
}
