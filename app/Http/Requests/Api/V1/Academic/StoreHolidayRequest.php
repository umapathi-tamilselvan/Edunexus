<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AcademicYear $year */
        $year = $this->route('academicYear');

        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->where('academic_year_id', $year->id)],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'That day is already a holiday.',
        ];
    }
}
