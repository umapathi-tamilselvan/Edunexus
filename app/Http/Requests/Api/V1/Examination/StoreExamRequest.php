<?php

namespace App\Http\Requests\Api\V1\Examination;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Examination\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Exam|null $exam */
        $exam = $this->route('exam');
        /** @var AcademicYear|null $year */
        $year = $this->route('academicYear');
        $yearId = $exam->academic_year_id ?? $year?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('exams', 'name')->where('academic_year_id', $yearId)->ignore($exam)],
            'max_marks' => ['required', 'integer', 'min:1', 'max:999'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
