<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    public function test_creates_a_super_admin_who_can_sign_in(): void
    {
        $this->configureSuperAdmin();

        $this->seed(UserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('Owner', $admin->name);
        $this->assertSame('owner@dev.test', $admin->email);
        $this->assertSame([Role::SuperAdmin->value], $admin->getRoleNames()->all());

        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'owner@dev.test',
            'password' => self::PASSWORD,
            'device_name' => 'phpunit',
        ])->assertCreated();
    }

    public function test_rerunning_keeps_one_user_and_their_changed_password(): void
    {
        $this->configureSuperAdmin();
        $this->seed(UserSeeder::class);
        User::query()->sole()->update(['password' => 'changed-after-seeding']);

        $this->seed(UserSeeder::class);

        $admin = User::query()->sole();
        $this->assertTrue(Hash::check('changed-after-seeding', $admin->password));
        $this->assertSame([Role::SuperAdmin->value], $admin->getRoleNames()->all());
    }

    public function test_promotes_an_existing_user_with_that_email(): void
    {
        $teacher = $this->createUser(Role::Teacher, ['email' => 'owner@dev.test']);
        $this->configureSuperAdmin();

        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue($teacher->fresh()->hasRole(Role::SuperAdmin->value));
    }

    #[TestWith(['email', 'not-an-email', 'EDUNEXUS_SUPER_ADMIN_EMAIL'], 'invalid email')]
    #[TestWith(['password', null, 'EDUNEXUS_SUPER_ADMIN_PASSWORD'], 'missing password')]
    #[TestWith(['password', 'short', 'EDUNEXUS_SUPER_ADMIN_PASSWORD'], 'short password')]
    public function test_rejects_incomplete_configuration_without_creating_a_user(string $key, ?string $value, string $variable): void
    {
        $this->configureSuperAdmin();
        config(["edunexus.super_admin.{$key}" => $value]);

        try {
            $this->seed(UserSeeder::class);
            $this->fail('The seeder accepted incomplete configuration.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($variable, $exception->getMessage());
        }

        $this->assertDatabaseEmpty('users');
    }

    public function test_skips_without_an_email_even_when_a_password_is_set(): void
    {
        $this->configureSuperAdmin();
        config(['edunexus.super_admin.email' => null]);

        $this->artisan('db:seed', ['--class' => UserSeeder::class])
            ->expectsOutputToContain('No Super Admin seeded')
            ->assertSuccessful();

        $this->assertDatabaseEmpty('users');
    }

    public function test_the_database_seeder_creates_the_configured_super_admin(): void
    {
        $this->configureSuperAdmin();

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->sole()->hasRole(Role::SuperAdmin->value));
    }

    private function configureSuperAdmin(): void
    {
        config(['edunexus.super_admin' => ['name' => 'Owner', 'email' => 'owner@dev.test', 'password' => self::PASSWORD]]);
    }
}
