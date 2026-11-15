<?php

namespace App\Http\Requests\Api\V1\Attendance;

use App\Domain\Attendance\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'period_id' => ['nullable', 'integer'],
            'entries' => ['present', 'array', 'max:500'],
            'entries.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'entries.*.status' => ['present', 'nullable', Rule::enum(AttendanceStatus::class)],
            'entries.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
