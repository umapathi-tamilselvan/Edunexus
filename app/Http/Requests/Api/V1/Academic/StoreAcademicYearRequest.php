<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicYearRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AcademicYear|null $year */
        $year = $this->route('academicYear');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('academic_years', 'name')->ignore($year)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'make_current' => ['sometimes', 'boolean'],
        ];
    }
}
