<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Academic\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
        ];
    }
}
