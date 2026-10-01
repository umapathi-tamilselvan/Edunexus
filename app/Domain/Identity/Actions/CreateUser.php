<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a login in this client and gives it its roles.
 */
class CreateUser
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(string $name, string $email, string $password, array $roles): User
    {
        return DB::transaction(function () use ($name, $email, $password, $roles): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->syncRoles($roles);

            return $user->load('roles');
        });
    }
}
