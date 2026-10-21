<?php

namespace App\Console\Commands;

use App\Support\Deployment\Client;
use App\Support\Deployment\ClientDatabase;
use App\Support\Deployment\ClientFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('edunexus:client:migrate {code? : Client code} {--all : Migrate every client} {--seed : Re-run the seeders afterwards (e.g. new permissions)}')]
#[Description('Run pending migrations for one client, or all clients')]
class ClientMigrateCommand extends Command
{
    public function handle(ClientFiles $files, ClientDatabase $database): int
    {
        if ($this->option('all') === (bool) $this->argument('code')) {
            $this->components->error('Pass a client code or --all.');

            return self::FAILURE;
        }

        try {
            $clients = $this->option('all') ? $files->all() : [$files->read((string) $this->argument('code'))];
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $this->components->task("Migrating [{$client->code}] {$client->database}", fn (): bool => $this->migrate($client, $database));
        }

        return self::SUCCESS;
    }

    private function migrate(Client $client, ClientDatabase $database): bool
    {
        return $database->run($client, function () use ($client): bool {
            $original = config()->getMany(['edunexus.client', 'edunexus.super_admin']);
            config([
                'edunexus.client' => ['code' => $client->code, 'name' => $client->name, 'type' => $client->type->value],
                // The root .env's Super Admin must never be seeded into a client.
                'edunexus.super_admin' => null,
            ]);

            try {
                return $this->callSilently('migrate', ['--force' => true]) === 0
                    && (! $this->option('seed') || $this->callSilently('db:seed', ['--force' => true]) === 0);
            } finally {
                config($original);
            }
        });
    }
}
