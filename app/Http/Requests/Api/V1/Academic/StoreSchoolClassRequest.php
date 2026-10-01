<?php

namespace App\Http\Requests\Api\V1\Academic;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SchoolClass|null $class */
        $class = $this->route('schoolClass');
        /** @var AcademicYear|null $year */
        $year = $this->route('academicYear');
        $academicYearId = $class->academic_year_id ?? $year?->id;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('classes', 'name')->where('academic_year_id', $academicYearId)->ignore($class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
