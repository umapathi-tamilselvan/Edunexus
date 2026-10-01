<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Academic\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'enrolled_count' => $this->whenCounted('enrollments'),
            'class_teacher' => $this->whenLoaded('classTeacher', fn () => $this->classTeacher ? ['id' => $this->classTeacher->id, 'name' => $this->classTeacher->name] : null),
        ];
    }
}
