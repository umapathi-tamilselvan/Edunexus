<?php

namespace App\Http\Requests\Api\V1\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributeStudentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', Rule::in(['name', 'admission_no'])],
        ];
    }
}
