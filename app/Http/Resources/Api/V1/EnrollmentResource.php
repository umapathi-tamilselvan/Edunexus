<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentEnrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'class' => $this->whenLoaded('section', fn () => ['id' => $this->section->schoolClass->id, 'name' => $this->section->schoolClass->name]),
            'section' => $this->whenLoaded('section', fn () => ['id' => $this->section->id, 'name' => $this->section->name]),
            'roll_no' => $this->roll_no,
            'status' => $this->status->value,
        ];
    }
}
