<?php

namespace Tests;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Support\Deployment\ClientFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const PASSWORD = 'correct-horse-battery-staple';

    /**
     * Seed roles and institution-type data, like a freshly created client.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['educore.provisioning.deployments_path' => storage_path('framework/testing/deployments')]);
    }

    /**
     * Never reset or drop anything outside the test databases.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $database = (string) config('database.connections.mysql.database');
        $prefix = (string) config('educore.provisioning.database_prefix');

        if (! str_ends_with($database, '_test') || ! str_contains($prefix, 'test')) {
            $this->fail("Refusing to run: tests reset [{$database}] and drop [{$prefix}*] databases and users. Use the phpunit.xml environment.");
        }
    }

    /**
     * Create a user in this client's database.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createUser(Role $role = Role::Teacher, array $attributes = []): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD, ...$attributes]);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Issue a bearer token by signing in through the API.
     */
    protected function issueToken(string $email): string
    {
        return $this->postJson('/api/v1/auth/tokens', [
            'email' => $email,
            'password' => self::PASSWORD,
            'device_name' => 'phpunit',
        ])->assertCreated()->json('token');
    }

    /**
     * Drop every client database, MySQL user and deployment directory that
     * tests created.
     */
    protected function dropTestClients(): void
    {
        $prefix = (string) config('educore.provisioning.database_prefix');
        $provisioning = DB::connection('provisioning');
        $pattern = str_replace('_', '\\_', $prefix).'%';

        foreach ($provisioning->select('SELECT schema_name AS name FROM information_schema.schemata WHERE schema_name LIKE ?', [$pattern]) as $row) {
            $provisioning->statement("DROP DATABASE `{$row->name}`");
        }

        foreach ($provisioning->select('SELECT user, host FROM mysql.user WHERE user LIKE ?', [$pattern]) as $row) {
            $provisioning->statement("DROP USER '{$row->user}'@'{$row->host}'");
        }

        File::deleteDirectory(app(ClientFiles::class)->root());
    }
}
