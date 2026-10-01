<?php

namespace App\Support\Deployment;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use RuntimeException;

/**
 * Boots the app as one client. With EDUCORE_CLIENT=abc, the environment
 * comes from deployments/abc/.env.secure (secrets) and deployments/abc/.env
 * (settings) instead of the root .env. Without it, the root .env is used as
 * the development client.
 */
class ClientEnvironment
{
    public const CODE_PATTERN = '/^[a-z0-9]{2,20}$/';

    public static function apply(Application $app): void
    {
        $client = Env::get('EDUCORE_CLIENT');

        if ($client === null || $client === '') {
            return;
        }

        if (! preg_match(self::CODE_PATTERN, $client)) {
            throw new RuntimeException("EDUCORE_CLIENT [{$client}] is not a valid client code.");
        }

        $directory = self::directory($app->basePath(), $client);

        if (! is_file($directory.'/.env') || ! is_file($directory.'/.env.secure')) {
            throw new RuntimeException("Client [{$client}] has no deployment files in [{$directory}]. Create it with: php artisan educore:client:create {$client}");
        }

        // Each client gets its own cached config, routes and events, so
        // `config:cache` for one client can never boot another with its
        // database credentials.
        foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes-v7.php', 'APP_EVENTS_CACHE' => 'events.php'] as $key => $file) {
            Env::getRepository()->set($key, $app->bootstrapPath("cache/clients/{$client}/{$file}"));
        }

        if (! is_dir($app->bootstrapPath("cache/clients/{$client}"))) {
            mkdir($app->bootstrapPath("cache/clients/{$client}"), 0755, true);
        }

        // The repository is immutable: values loaded first win, so secrets in
        // .env.secure cannot be overridden by .env.
        Dotenv::create(Env::getRepository(), $directory, '.env.secure')->load();

        $app->useEnvironmentPath($directory);
        $app->loadEnvironmentFrom('.env');
    }

    public static function directory(string $basePath, string $client): string
    {
        return rtrim((string) (Env::get('EDUCORE_DEPLOYMENTS_PATH') ?: $basePath.'/deployments'), '/').'/'.$client;
    }
}
