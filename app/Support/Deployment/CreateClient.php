<?php

namespace App\Support\Deployment;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Support\OrganizationContext;
use App\Support\Settings\Setting;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Creates a client: its deployment files, its own MySQL database and user,
 * its schema and institution-type data, and a Super Admin who can sign in.
 * The same codebase then runs as that client with EDUNEXUS_CLIENT=<code>.
 */
class CreateClient
{
    public function __construct(
        private ClientFiles $files,
        private ClientDatabase $database,
        private Artisan $artisan,
        private PermissionRegistrar $permissions,
    ) {}

    /**
     * @return array{client: Client, admin_email: string, admin_password: string, academic_year: string, redis_access: string|null}
     */
    public function handle(
        string $code,
        string $name,
        string $type,
        string $adminEmail,
        ?string $adminName = null,
        #[\SensitiveParameter] ?string $adminPassword = null,
        ?string $url = null,
        ?int $yearStartMonth = null,
    ): array {
        // 1. Validate.
        $institutionType = $this->validate($code, $name, $type, $adminEmail, $yearStartMonth);

        $prefix = (string) config('edunexus.provisioning.database_prefix');
        $client = new Client(
            code: $code,
            name: $name,
            type: $institutionType,
            url: $url ?? (string) config('edunexus.provisioning.default_url'),
            database: $prefix.$code,
            username: $prefix.$code,
            password: Str::password(32, symbols: false),
        );

        if ($this->database->databaseExists($client->database) || $this->database->userExists($client->username)) {
            throw new InvalidArgumentException("Database or MySQL user [{$client->database}] already exists.");
        }

        $adminPassword ??= Str::password(16, symbols: false);
        $academicYear = '';

        try {
            // 2–3. Write deployments/<code>/.env and .env.secure.
            $this->files->write($code, $this->settings($client), $this->secrets($client));

            // 4. Provision the database and its user.
            $this->database->provision($client);

            // 5–6. Migrate, seed (which opens the first academic year) and
            // create the admin, connected as the client's user.
            $this->database->run($client, function () use ($client, $adminEmail, $adminName, $adminPassword, $yearStartMonth, &$academicYear): void {
                $this->asClient($client, function () use ($adminEmail, $adminName, $adminPassword, $yearStartMonth, &$academicYear): void {
                    $this->call('migrate', ['--force' => true]);

                    // Before seeding, so the seeders keep it instead of the type's default.
                    if ($yearStartMonth !== null) {
                        Setting::query()->create(['key' => OrganizationContext::YEAR_START_MONTH, 'value' => $yearStartMonth]);
                    }

                    $this->call('db:seed', ['--force' => true]);

                    $year = AcademicYear::query()->current()->sole();
                    $academicYear = "{$year->name} ({$year->starts_on->format('j M Y')} – {$year->ends_on->format('j M Y')})";

                    User::query()
                        ->create([
                            'name' => $adminName ?? 'Super Admin',
                            'email' => $adminEmail,
                            'password' => $adminPassword,
                        ])
                        ->assignRole(Role::SuperAdmin->value);
                });
            });
        } catch (Throwable $exception) {
            $this->database->drop($client);
            $this->files->delete($code);

            throw $exception;
        }

        return [
            'client' => $client,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
            'academic_year' => $academicYear,
            'redis_access' => $this->usesRedis() ? self::redisAccess($code) : null,
        ];
    }

    /**
     * The access string for the client's Redis user: its own key prefix
     * (the default REDIS_PREFIX) and nothing that could reach other
     * clients' keys, such as FLUSHDB or KEYS.
     */
    public static function redisAccess(string $code): string
    {
        return "on ~edunexus:{$code}:* &edunexus:{$code}:* +@all -@dangerous";
    }

    private function validate(string $code, string $name, string $type, string $adminEmail, ?int $yearStartMonth): InstitutionType
    {
        if (! preg_match(ClientEnvironment::CODE_PATTERN, $code)) {
            throw new InvalidArgumentException("Client code [{$code}] must be 2 to 20 lowercase letters or digits.");
        }

        if ($this->files->exists($code)) {
            throw new InvalidArgumentException("Client [{$code}] already exists in [{$this->files->root()}].");
        }

        $institutionType = InstitutionType::tryFrom($type)
            ?? throw new InvalidArgumentException("Type [{$type}] is not valid. Use one of: ".implode(', ', array_column(InstitutionType::cases(), 'value')).'.');

        if (trim($name) === '') {
            throw new InvalidArgumentException('The institution name is required.');
        }

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Admin email [{$adminEmail}] is not a valid email address.");
        }

        if ($yearStartMonth !== null && ($yearStartMonth < 1 || $yearStartMonth > 12)) {
            throw new InvalidArgumentException('The year start month must be a month number from 1 to 12.');
        }

        return $institutionType;
    }

    /**
     * Run seeders and model code with the client's identity in config, so
     * the institution-type seeder sees the new client, not the one running
     * the command, and its settings rather than any already loaded. The
     * root .env's Super Admin is cleared so UserSeeder never adds it to the
     * client.
     */
    private function asClient(Client $client, callable $callback): void
    {
        $original = config()->getMany(['edunexus.client', 'edunexus.super_admin']);

        config([
            'edunexus.client' => ['code' => $client->code, 'name' => $client->name, 'type' => $client->type->value],
            'edunexus.super_admin' => null,
        ]);
        $this->permissions->initializeCache();
        app()->forgetInstance(OrganizationContext::class);

        try {
            $callback();
        } finally {
            config($original);
            $this->permissions->initializeCache();
            app()->forgetInstance(OrganizationContext::class);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function call(string $command, array $parameters): void
    {
        if ($this->artisan->call($command, $parameters) !== 0) {
            throw new RuntimeException("[{$command}] failed for the client database: ".trim($this->artisan->output()));
        }
    }

    /**
     * The client's environment: the one creating it, with tests creating
     * local clients.
     */
    private function environment(): string
    {
        return app()->environment('testing') ? 'local' : (string) app()->environment();
    }

    /**
     * Local clients keep sessions, cache and queues in their database;
     * every other client uses the shared Redis.
     */
    private function usesRedis(): bool
    {
        return $this->environment() !== 'local';
    }

    /**
     * Non-secret settings: a complete environment for the client deployment.
     *
     * @return array<string, string>
     */
    private function settings(Client $client): array
    {
        $environment = $this->environment();
        $local = $environment === 'local';
        $driver = $this->usesRedis() ? 'redis' : 'database';

        return [
            'APP_NAME' => 'EduNexus',
            'APP_ENV' => $environment,
            'APP_DEBUG' => $local ? 'true' : 'false',
            'APP_URL' => $client->url,
            'APP_LOCALE' => 'en',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'en_IN',
            'BCRYPT_ROUNDS' => '12',
            '#client' => 'EduNexus client',
            'EDUNEXUS_CLIENT_CODE' => $client->code,
            'EDUNEXUS_INSTITUTION_TYPE' => $client->type->value,
            'EDUNEXUS_ORG_NAME' => $client->name,
            'RECEIPT_PREFIX' => strtoupper($client->code),
            'EDUNEXUS_MODULES_ENABLE' => '',
            'EDUNEXUS_MODULES_DISABLE' => '',
            '#infrastructure' => 'Infrastructure',
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'single',
            'LOG_LEVEL' => $local ? 'debug' : 'info',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.provisioning.host'),
            'DB_PORT' => (string) config('database.connections.provisioning.port'),
            'SESSION_DRIVER' => $driver,
            'SESSION_LIFETIME' => '120',
            'SESSION_ENCRYPT' => 'false',
            'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => 'null',
            'CACHE_STORE' => $driver,
            'QUEUE_CONNECTION' => $driver,
            ...($this->usesRedis() ? [
                'REDIS_HOST' => (string) config('database.redis.default.host'),
                'REDIS_PORT' => (string) config('database.redis.default.port'),
                'REDIS_USERNAME' => $client->username,
            ] : []),
            'FILESYSTEM_DISK' => 'local',
            'BROADCAST_CONNECTION' => 'log',
            'MAIL_MAILER' => 'log',
            'MAIL_FROM_ADDRESS' => 'no-reply@'.$client->code.'.edunexus.in',
            'MAIL_FROM_NAME' => $client->name,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function secrets(Client $client): array
    {
        return [
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_DATABASE' => $client->database,
            'DB_USERNAME' => $client->username,
            'DB_PASSWORD' => $client->password,
            ...($this->usesRedis() ? ['REDIS_PASSWORD' => Str::password(32, symbols: false)] : []),
        ];
    }
}
