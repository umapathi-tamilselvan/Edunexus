<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Period|null $period */
        $period = $this->route('period');
        /** @var AcademicYear|null $year */
        $year = $this->route('academicYear');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('periods', 'name')->where('academic_year_id', $period->academic_year_id ?? $year?->id)->ignore($period)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }
}
