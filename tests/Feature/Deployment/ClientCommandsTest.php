<?php

namespace Tests\Feature\Deployment;

use App\Support\Deployment\ClientDatabase;
use App\Support\Deployment\ClientFiles;
use App\Support\Deployment\CreateClient;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ClientCommandsTest extends TestCase
{
    private const PREFIX = 'edunexus_testclient_';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestClients();
        $this->beforeApplicationDestroyed(fn () => $this->dropTestClients());
    }

    public function test_create_writes_env_files_provisions_a_database_and_user_and_creates_the_super_admin(): void
    {
        $this->artisan('edunexus:client:create', [
            'code' => 'abc',
            '--name' => 'St. Mary\'s "Higher" School',
            '--type' => 'school',
            '--admin-email' => 'owner@abc.test',
            '--url' => 'https://abc.edunexus.in',
        ])
            ->expectsOutputToContain('Client [abc] created.')
            ->expectsOutputToContain(self::PREFIX.'abc')
            ->expectsOutputToContain('https://abc.edunexus.in/login')
            ->expectsOutputToContain('This password is shown only once.')
            ->assertSuccessful();

        $client = app(ClientFiles::class)->read('abc');
        $this->assertSame('St. Mary\'s "Higher" School', $client->name);
        $this->assertSame(self::PREFIX.'abc', $client->database);
        $this->assertSame(self::PREFIX.'abc', $client->username);
        $this->assertSame(32, strlen($client->password));

        $files = app(ClientFiles::class);
        $this->assertSame($files->directory('abc').'/abc.env', $files->settingsPath('abc'));
        $this->assertSame($files->directory('abc').'/abc.env.secure', $files->secretsPath('abc'));
        $settings = file_get_contents($files->settingsPath('abc'));
        $secrets = file_get_contents($files->secretsPath('abc'));
        $this->assertStringContainsString("EDUNEXUS_CLIENT_CODE=abc\n", $settings);
        $this->assertStringContainsString("EDUNEXUS_INSTITUTION_TYPE=school\n", $settings);
        $this->assertStringContainsString("RECEIPT_PREFIX=ABC\n", $settings);
        $this->assertStringContainsString("SESSION_DRIVER=database\nSESSION_LIFETIME", $settings);
        $this->assertStringContainsString("CACHE_STORE=database\nQUEUE_CONNECTION=database\n", $settings);
        $this->assertStringNotContainsString('REDIS_', $settings.$secrets);
        $this->assertStringNotContainsString('DB_PASSWORD', $settings);
        $this->assertStringNotContainsString('APP_KEY', $settings);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{44}$/m', $secrets);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($files->secretsPath('abc'))), -4));

        $this->asClient('abc', function (): void {
            $this->assertSame(self::PREFIX.'abc@%', DB::selectOne('SELECT CURRENT_USER() AS user')->user);
            $this->assertSame(['owner@abc.test'], DB::table('users')->pluck('email')->all());
            $this->assertSame('Super Admin', DB::table('roles')->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')->value('roles.name'));
        });
    }

    public function test_a_production_client_uses_redis_as_its_own_user_limited_to_its_keys(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC School', '--type' => 'school', '--admin-email' => 'owner@abc.test'])
            ->expectsOutputToContain(self::PREFIX.'abc (password: REDIS_PASSWORD in abc.env.secure)')
            ->expectsOutputToContain('on ~edunexus:abc:* &edunexus:abc:* +@all -@dangerous')
            ->expectsOutputToContain('Create the Redis user '.self::PREFIX.'abc with this access before deploying.')
            ->assertSuccessful();

        $files = app(ClientFiles::class);
        $settings = file_get_contents($files->settingsPath('abc'));
        $secrets = file_get_contents($files->secretsPath('abc'));
        $this->assertStringContainsString("SESSION_DRIVER=redis\n", $settings);
        $this->assertStringContainsString("CACHE_STORE=redis\nQUEUE_CONNECTION=redis\n", $settings);
        $this->assertStringContainsString('REDIS_USERNAME='.self::PREFIX."abc\n", $settings);
        $this->assertStringNotContainsString('REDIS_PASSWORD', $settings);
        $this->assertMatchesRegularExpression('/^REDIS_PASSWORD=[A-Za-z0-9]{32}$/m', $secrets);
    }

    public function test_the_client_user_cannot_read_other_databases(): void
    {
        $this->createClient('abc');

        $this->asClient('abc', function (): void {
            $this->expectExceptionMessage('SELECT command denied');

            DB::select('SELECT COUNT(*) FROM edunexus_test.users');
        });
    }

    public function test_the_generated_password_is_the_admins_login_password(): void
    {
        $result = $this->createClient('abc');

        $this->asClient('abc', function () use ($result): void {
            $this->assertTrue(Hash::check($result['admin_password'], DB::table('users')->value('password')));
        });
    }

    public function test_ask_password_uses_the_typed_password_and_never_prints_it(): void
    {
        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'school', '--admin-email' => 'owner@abc.test', '--ask-password' => true])
            ->expectsQuestion('Super Admin password (min. 8 characters)', 'Typed@Pass123')
            ->expectsQuestion('Confirm password', 'Typed@Pass123')
            ->doesntExpectOutputToContain('Typed@Pass123')
            ->doesntExpectOutputToContain('shown only once')
            ->assertSuccessful();

        $this->asClient('abc', function (): void {
            $this->assertTrue(Hash::check('Typed@Pass123', DB::table('users')->value('password')));
        });
    }

    public function test_ask_password_rejects_a_mismatched_confirmation(): void
    {
        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'school', '--admin-email' => 'owner@abc.test', '--ask-password' => true])
            ->expectsQuestion('Super Admin password (min. 8 characters)', 'Typed@Pass123')
            ->expectsQuestion('Confirm password', 'Other@Pass123')
            ->expectsOutputToContain('The passwords do not match.')
            ->assertFailed();

        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    public function test_the_institution_type_seeds_its_own_roles_terminology_and_year_start_month(): void
    {
        $this->createClient('abc', 'college');

        $this->asClient('abc', function (): void {
            $roles = DB::table('roles')->pluck('name')->all();

            $this->assertContains('Super Admin', $roles);
            $this->assertNotContains('Guardian', $roles);
            // MySQL JSON columns do not keep key order.
            $this->assertEquals(
                ['class' => 'Semester', 'classes' => 'Semesters', 'section' => 'Division', 'sections' => 'Divisions', 'teacher' => 'Faculty', 'head' => 'Principal'],
                json_decode(DB::table('settings')->where('key', 'institution.terminology')->value('value'), true),
            );
            $this->assertSame(7, json_decode(DB::table('settings')->where('key', 'academic_year.start_month')->value('value')));
        });
    }

    public function test_the_year_start_month_option_sets_when_the_first_academic_year_starts_and_survives_reseeding(): void
    {
        $this->travelTo('2026-10-26');

        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'school', '--admin-email' => 'owner@abc.test', '--year-start-month' => '4'])
            ->expectsOutputToContain('2026-27 (1 Apr 2026 – 31 Mar 2027)')
            ->assertSuccessful();

        $this->artisan('edunexus:client:migrate', ['code' => 'abc', '--seed' => true])->assertSuccessful();

        $this->asClient('abc', function (): void {
            $this->assertSame(4, json_decode(DB::table('settings')->where('key', 'academic_year.start_month')->value('value')));
            $this->assertSame([['name' => '2026-27', 'is_current' => 1]], DB::table('academic_years')->get(['name', 'is_current'])->map(fn ($year): array => (array) $year)->all());
        });
    }

    #[TestWith(['13'])]
    #[TestWith(['0'])]
    #[TestWith(['june'])]
    public function test_create_rejects_a_year_start_month_that_is_not_a_month(string $month): void
    {
        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'school', '--admin-email' => 'owner@abc.test', '--year-start-month' => $month])
            ->expectsOutputToContain('The year start month must be a month number from 1 to 12.')
            ->assertFailed();

        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    #[TestWith(['a', 'must be 2 to 20 lowercase letters or digits'])]
    #[TestWith(['ABC', 'must be 2 to 20 lowercase letters or digits'])]
    #[TestWith(['ab-c', 'must be 2 to 20 lowercase letters or digits'])]
    #[TestWith(['abcdefghijklmnopqrstu', 'must be 2 to 20 lowercase letters or digits'])]
    public function test_create_rejects_an_invalid_code(string $code, string $message): void
    {
        $this->artisan('edunexus:client:create', ['code' => $code, '--name' => 'X', '--type' => 'school', '--admin-email' => 'owner@x.test'])
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertSame([], app(ClientFiles::class)->all());
    }

    public function test_create_rejects_an_unknown_type(): void
    {
        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'shop', '--admin-email' => 'owner@abc.test'])
            ->expectsOutputToContain('Type [shop] is not valid. Use one of: school, college, coaching.')
            ->assertFailed();

        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    public function test_create_rejects_a_code_already_in_use(): void
    {
        $this->createClient('abc');

        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'Again', '--type' => 'school', '--admin-email' => 'owner@abc.test'])
            ->expectsOutputToContain('Client [abc] already exists')
            ->assertFailed();
    }

    public function test_create_rejects_a_code_whose_database_already_exists(): void
    {
        DB::connection('provisioning')->statement('CREATE DATABASE `'.self::PREFIX.'abc`');

        $this->artisan('edunexus:client:create', ['code' => 'abc', '--name' => 'ABC', '--type' => 'school', '--admin-email' => 'owner@abc.test'])
            ->expectsOutputToContain('Database or MySQL user ['.self::PREFIX.'abc] already exists.')
            ->assertFailed();

        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    public function test_a_failed_create_removes_the_database_user_and_files(): void
    {
        Event::listen(MigrationsEnded::class, fn () => throw new RuntimeException('Migration step failed.'));

        try {
            $this->createClient('abc');
            $this->fail('Expected client creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Migration step failed.', $exception->getMessage());
        }

        $database = app(ClientDatabase::class);
        $this->assertFalse($database->databaseExists(self::PREFIX.'abc'));
        $this->assertFalse($database->userExists(self::PREFIX.'abc'));
        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    public function test_list_shows_every_client_with_type_and_database(): void
    {
        $this->createClient('abc', 'school');
        $this->createClient('xyz', 'coaching');

        $this->artisan('edunexus:client:list')
            ->expectsTable(['Code', 'Name', 'Type', 'Database', 'URL'], [
                ['abc', 'ABC Institution', 'school', self::PREFIX.'abc', 'http://localhost:8000'],
                ['xyz', 'XYZ Institution', 'coaching', self::PREFIX.'xyz', 'http://localhost:8000'],
            ])
            ->assertSuccessful();
    }

    public function test_migrate_runs_pending_migrations_for_one_client_or_all(): void
    {
        $this->createClient('abc');
        $this->createClient('xyz');

        $this->asClient('abc', fn () => DB::table('migrations')->where('migration', 'like', '%create_settings_table')->delete());
        $this->asClient('abc', fn () => DB::statement('DROP TABLE settings'));

        $this->artisan('edunexus:client:migrate', ['code' => 'abc'])->assertSuccessful();
        $this->asClient('abc', fn () => $this->assertTrue(DB::getSchemaBuilder()->hasTable('settings')));

        $this->artisan('edunexus:client:migrate', ['--all' => true])
            ->expectsOutputToContain('Migrating [abc]')
            ->expectsOutputToContain('Migrating [xyz]')
            ->assertSuccessful();
    }

    public function test_the_root_super_admin_is_never_seeded_into_a_client(): void
    {
        config(['edunexus.super_admin' => ['name' => 'Dev Admin', 'email' => 'dev@root.test', 'password' => self::PASSWORD]]);
        $this->createClient('abc');

        $this->artisan('edunexus:client:migrate', ['code' => 'abc', '--seed' => true])->assertSuccessful();

        $this->asClient('abc', fn () => $this->assertSame(['owner@abc.test'], DB::table('users')->pluck('email')->all()));
        $this->assertSame('dev@root.test', config('edunexus.super_admin.email'));
    }

    public function test_migrate_needs_exactly_one_of_a_code_or_all(): void
    {
        $this->artisan('edunexus:client:migrate')->expectsOutputToContain('Pass a client code or --all.')->assertFailed();
        $this->artisan('edunexus:client:migrate', ['code' => 'nope'])->expectsOutputToContain('Client [nope] not found')->assertFailed();
    }

    public function test_drop_removes_the_database_user_and_files(): void
    {
        $this->createClient('abc');

        $this->artisan('edunexus:client:drop', ['code' => 'abc', '--force' => true])->assertSuccessful();

        $database = app(ClientDatabase::class);
        $this->assertFalse($database->databaseExists(self::PREFIX.'abc'));
        $this->assertFalse($database->userExists(self::PREFIX.'abc'));
        $this->assertFalse(app(ClientFiles::class)->exists('abc'));
    }

    public function test_drop_requires_force(): void
    {
        $this->createClient('abc');

        $this->artisan('edunexus:client:drop', ['code' => 'abc'])
            ->expectsOutputToContain('Re-run with --force to confirm.')
            ->assertFailed();

        $this->assertTrue(app(ClientDatabase::class)->databaseExists(self::PREFIX.'abc'));
    }

    public function test_drop_refuses_in_production(): void
    {
        $this->createClient('abc');
        $this->app['env'] = 'production';

        $this->artisan('edunexus:client:drop', ['code' => 'abc', '--force' => true])
            ->expectsOutputToContain('only runs in local and testing environments')
            ->assertFailed();

        $this->assertTrue(app(ClientDatabase::class)->databaseExists(self::PREFIX.'abc'));
    }

    public function test_the_app_boots_as_a_client_from_its_deployment_files(): void
    {
        $this->createClient('abc', 'coaching');

        $output = $this->runAsClient('abc', 'echo json_encode(['
            .'"client" => config("edunexus.client"),'
            .'"database" => DB::connection()->getDatabaseName(),'
            .'"user" => DB::selectOne("SELECT CURRENT_USER() AS u")->u,'
            .'"admins" => App\Domain\Identity\Models\User::role("Super Admin")->pluck("email"),'
            .'"storage" => storage_path(),'
            .'"files" => Storage::disk("local")->path("report.pdf"),'
            .']);');

        $this->assertSame([
            'client' => ['code' => 'abc', 'name' => 'ABC Institution', 'type' => 'coaching'],
            'database' => self::PREFIX.'abc',
            'user' => self::PREFIX.'abc@%',
            'admins' => ['owner@abc.test'],
            'storage' => $this->clientStorage(),
            'files' => $this->clientStorage().'/app/private/report.pdf',
        ], json_decode($output, true));
        $this->assertDirectoryExists($this->clientStorage().'/framework/views');
    }

    public function test_each_client_gets_its_own_redis_key_prefix_and_session_cookie(): void
    {
        $this->createClient('abc');
        $this->createClient('xyz');
        $keys = fn (string $code): array => json_decode($this->runAsClient($code, 'echo json_encode(['
            .'"redis" => config("database.redis.options.prefix"),'
            .'"horizon" => config("horizon.prefix"),'
            .'"cookie" => config("session.cookie"),'
            .']);'), true);

        $this->assertSame(['redis' => 'edunexus:abc:', 'horizon' => 'edunexus:abc:horizon:', 'cookie' => 'edunexus_abc_session'], $keys('abc'));
        $this->assertSame(['redis' => 'edunexus:xyz:', 'horizon' => 'edunexus:xyz:horizon:', 'cookie' => 'edunexus_xyz_session'], $keys('xyz'));
    }

    public function test_each_client_caches_its_config_separately(): void
    {
        $this->createClient('abc');
        $cachedConfig = base_path('bootstrap/cache/clients/abc/config.php');

        try {
            $this->clientProcess('abc', ['config:cache'])->mustRun();

            $this->assertFileExists($cachedConfig);
            $this->assertSame(self::PREFIX.'abc', (require $cachedConfig)['database']['connections']['mysql']['database']);
            $this->assertSame(self::PREFIX.'abc', $this->runAsClient('abc', 'echo DB::connection()->getDatabaseName();'));
        } finally {
            @unlink($cachedConfig);
        }
    }

    public function test_booting_as_a_client_without_deployment_files_fails_loudly(): void
    {
        $process = $this->clientProcess('nope', ['tinker', '--execute', 'echo "booted";']);
        $process->run();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Client [nope] has no deployment files', $process->getErrorOutput().$process->getOutput());
    }

    private function clientStorage(): string
    {
        return storage_path('framework/testing/client-storage');
    }

    /**
     * @return array{admin_password: string}
     */
    private function createClient(string $code, string $type = 'school'): array
    {
        return app(CreateClient::class)->handle(
            code: $code,
            name: strtoupper($code).' Institution',
            type: $type,
            adminEmail: "owner@{$code}.test",
        );
    }

    private function asClient(string $code, callable $callback): void
    {
        app(ClientDatabase::class)->run(app(ClientFiles::class)->read($code), $callback);
    }

    /**
     * Run PHP inside a fresh app booted as the client, with a clean
     * environment so nothing from phpunit.xml or the root .env leaks in.
     */
    private function runAsClient(string $code, string $php): string
    {
        $process = $this->clientProcess($code, ['tinker', '--execute', $php]);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @param  list<string>  $artisan  Artisan command and arguments.
     */
    private function clientProcess(string $code, array $artisan): Process
    {
        $environment = array_map(fn (): bool => false, getenv());

        return new Process(
            [PHP_BINARY, 'artisan', ...$artisan],
            base_path(),
            [
                ...$environment,
                'PATH' => (string) getenv('PATH'),
                'HOME' => (string) getenv('HOME'),
                'EDUNEXUS_CLIENT' => $code,
                'EDUNEXUS_DEPLOYMENTS_PATH' => app(ClientFiles::class)->root(),
                'EDUNEXUS_STORAGE_PATH' => $this->clientStorage(),
            ],
            timeout: 60,
        );
    }
}
