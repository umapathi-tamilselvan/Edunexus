<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a user's roles. An institution always keeps at least one Super Admin.
 */
class SyncUserRoles
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles): User {
            $losesSuperAdmin = $user->hasRole(Role::SuperAdmin->value)
                && ! in_array(Role::SuperAdmin->value, $roles, true);

            if ($losesSuperAdmin && $this->superAdminCount() === 1) {
                throw ValidationException::withMessages([
                    'roles' => 'The institution must keep at least one Super Admin.',
                ]);
            }

            $user->syncRoles($roles);

            return $user->load('roles');
        });
    }

    /**
     * Counted under a row lock so two concurrent demotions cannot both pass.
     */
    private function superAdminCount(): int
    {
        return User::query()->role(Role::SuperAdmin->value)->lockForUpdate()->count();
    }
}
