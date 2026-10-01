<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UserManage->value);
    }

    /**
     * @param  list<string>  $roles  Roles the new user will get.
     */
    public function create(User $actor, array $roles = []): bool
    {
        return $actor->can(Permission::UserManage->value)
            && $this->mayGrant($actor, $roles);
    }

    /**
     * @param  list<string>  $roles  Roles the user will have afterwards.
     */
    public function updateRoles(User $actor, User $user, array $roles = []): bool
    {
        if (! $actor->can(Permission::UserManage->value)) {
            return false;
        }

        // Only a Super Admin may change another Super Admin's roles.
        if ($user->hasRole(Role::SuperAdmin->value) && ! $actor->hasRole(Role::SuperAdmin->value)) {
            return false;
        }

        return $this->mayGrant($actor, $roles);
    }

    /**
     * Super Admin can only be granted by a Super Admin.
     *
     * @param  list<string>  $roles
     */
    private function mayGrant(User $actor, array $roles): bool
    {
        return ! in_array(Role::SuperAdmin->value, $roles, true)
            || $actor->hasRole(Role::SuperAdmin->value);
    }
}
