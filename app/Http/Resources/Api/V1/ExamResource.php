<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Examination\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'name' => $this->name,
            'max_marks' => $this->max_marks,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'has_marks' => $this->whenExistsLoaded('marks', fn (bool $exists): bool => $exists),
            'is_published' => $this->isPublished(),
            'published_at' => $this->published_at?->toIso8601String(),
            'published_by' => $this->published_by,
        ];
    }
}
