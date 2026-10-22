<?php

namespace App\Http\Requests\Api\V1\Examination;

use Illuminate\Foundation\Http\FormRequest;

class SetExamMaxMarksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'max_marks' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
