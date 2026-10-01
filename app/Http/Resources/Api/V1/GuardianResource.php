<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Student\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phone numbers are shown in full only to users who manage students.
 *
 * @mixin Guardian
 */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeePhone = (bool) $request->user()?->can(Permission::StudentManage->value);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $canSeePhone ? $this->phone : str_repeat('•', 6).substr($this->phone, -4),
            'email' => $this->email,
            'relation' => $this->whenPivotLoaded('guardian_student', fn () => $this->pivot->relation),
            'is_primary' => $this->whenPivotLoaded('guardian_student', fn () => (bool) $this->pivot->is_primary),
        ];
    }
}
