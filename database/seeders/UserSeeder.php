<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Creates a Super Admin from EDUNEXUS_SUPER_ADMIN_*, e.g. to sign in to the
 * development client. Skipped when no email is set.
 *
 * edunexus:client:create and edunexus:client:migrate --seed seed clients
 * from the root process, so they clear this config first; otherwise the
 * root .env's credentials would reach every client's database.
 *
 * Safe to rerun: an existing user with that email keeps their password and
 * gains the Super Admin role.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('edunexus.super_admin.email');
        $password = (string) config('edunexus.super_admin.password');

        if ($email === '') {
            $this->command?->outputComponents()->warn('No Super Admin seeded: EDUNEXUS_SUPER_ADMIN_EMAIL is not set.');

            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Set EDUNEXUS_SUPER_ADMIN_EMAIL to a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Set EDUNEXUS_SUPER_ADMIN_PASSWORD to at least 8 characters.');
        }

        $this->call(InstitutionTypeSeeder::class);

        User::query()
            ->firstOrCreate(
                ['email' => $email],
                ['name' => (string) config('edunexus.super_admin.name'), 'password' => $password],
            )
            ->assignRole(Role::SuperAdmin->value);
    }
}
