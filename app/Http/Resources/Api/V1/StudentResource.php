<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'gender' => $this->gender->value,
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'enrollments' => EnrollmentResource::collection($this->whenLoaded('enrollments')),
        ];
    }
}
