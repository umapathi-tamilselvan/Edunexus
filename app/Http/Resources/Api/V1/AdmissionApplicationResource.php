<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Admission\Models\AdmissionApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only users with admission.manage can reach applications, so guardian
 * contact details are shown in full.
 *
 * @mixin AdmissionApplication
 */
class AdmissionApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_no' => $this->application_no,
            'status' => $this->status->value,
            'is_open' => $this->status->isOpen(),
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'class' => $this->whenLoaded('schoolClass', fn () => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'student_name' => $this->student_name,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'gender' => $this->gender->value,
            'previous_school' => $this->previous_school,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'guardian_email' => $this->guardian_email,
            'guardian_relation' => $this->guardian_relation->value,
            'notes' => $this->notes,
            'decision_reason' => $this->decision_reason,
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? ['id' => $this->decidedBy->id, 'name' => $this->decidedBy->name] : null),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => $this->student ? ['id' => $this->student->id, 'admission_no' => $this->student->admission_no] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
