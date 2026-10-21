<?php

namespace App\Http\Requests\Api\V1\Admission;

use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Enums\GuardianRelation;
use App\Http\Requests\Api\V1\Student\GuardianRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $guardian = GuardianRules::for();

        return [
            'class_id' => [$required, 'integer', Rule::exists('classes', 'id')],
            'student_name' => [$required, 'string', 'max:255'],
            'date_of_birth' => [$required, 'date', 'before:today'],
            'gender' => [$required, Rule::enum(Gender::class)],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'guardian_name' => [$required, ...array_slice($guardian['name'], 1)],
            'guardian_phone' => [$required, ...array_slice($guardian['phone'], 1)],
            'guardian_email' => $guardian['email'],
            'guardian_relation' => [$required, Rule::enum(GuardianRelation::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
