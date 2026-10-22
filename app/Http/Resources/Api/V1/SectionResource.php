<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\SectionSubjectTeacher;
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
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'academic_year' => $this->schoolClass->relationLoaded('academicYear')
                    ? ['id' => $this->schoolClass->academicYear->id, 'name' => $this->schoolClass->academicYear->name, 'is_current' => $this->schoolClass->academicYear->is_current]
                    : null,
                'subjects' => $this->when($this->schoolClass->relationLoaded('subjects'), fn () => SubjectResource::collection($this->schoolClass->subjects)),
            ]),
            'subject_teachers_count' => $this->whenCounted('subjectTeachers'),
            'subject_teachers' => $this->whenLoaded('subjectTeachers', fn () => $this->subjectTeachers->map(fn (SectionSubjectTeacher $assignment): array => [
                'subject_id' => $assignment->subject_id,
                'teacher' => $assignment->relationLoaded('teacher') ? ['id' => $assignment->teacher->id, 'name' => $assignment->teacher->name] : null,
            ])->values()),
        ];
    }
}
