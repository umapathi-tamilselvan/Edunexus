<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a login in this client and gives it its roles and, for a
 * teacher, the subjects they teach.
 */
class CreateUser
{
    /**
     * @param  list<string>  $roles
     * @param  list<int>  $subjectIds
     */
    public function handle(string $name, string $email, string $password, array $roles, array $subjectIds = []): User
    {
        return DB::transaction(function () use ($name, $email, $password, $roles, $subjectIds): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                // Kept for the previous release, which reads one subject per teacher.
                'subject_id' => $subjectIds[0] ?? null,
            ]);

            $user->syncRoles($roles);
            $user->subjects()->sync($subjectIds);

            return $user->load(['roles', 'subjects']);
        });
    }
}
