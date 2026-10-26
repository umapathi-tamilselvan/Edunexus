<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Academic\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimetableEntry
 */
class TimetableEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday->value,
            'period_id' => $this->period_id,
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'code' => $this->subject->code, 'name' => $this->subject->name]),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->name] : null),
            'is_override' => $this->is_override,
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
                'class' => $this->section->relationLoaded('schoolClass') ? $this->section->schoolClass->name : null,
            ]),
        ];
    }
}
