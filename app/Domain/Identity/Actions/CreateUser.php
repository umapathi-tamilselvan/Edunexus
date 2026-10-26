<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a login in this client and gives it its roles and, for a
 * teacher, the subject they teach.
 */
class CreateUser
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(string $name, string $email, string $password, array $roles, ?int $subjectId = null): User
    {
        return DB::transaction(function () use ($name, $email, $password, $roles, $subjectId): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'subject_id' => $subjectId,
            ]);

            $user->syncRoles($roles);

            return $user->load(['roles', 'subject']);
        });
    }
}
