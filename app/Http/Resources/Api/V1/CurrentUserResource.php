<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\User;
use App\Support\Modules\Entitlements;
use App\Support\Modules\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'modules' => array_map(fn (Module $module): string => $module->value, app(Entitlements::class)->modules()),
        ];
    }
}
