<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Roles available when creating users.
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $roles = Role::query()->with('permissions')->orderBy('id')->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role): array => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
            ]),
        ]);
    }
}
