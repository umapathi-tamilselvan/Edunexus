<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicYear
 */
class AcademicYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'is_current' => $this->is_current,
            'admissions_open' => $this->admissions_open,
            'classes' => SchoolClassResource::collection($this->whenLoaded('classes')),
        ];
    }
}
