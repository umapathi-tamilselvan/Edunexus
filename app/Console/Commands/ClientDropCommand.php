<?php

namespace App\Console\Commands;

use App\Support\Deployment\ClientDatabase;
use App\Support\Deployment\ClientFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('edunexus:client:drop {code : Client code} {--force : Confirm dropping the database, MySQL user and files}')]
#[Description('Local and testing only: drop a client\'s database, MySQL user and deployment files')]
class ClientDropCommand extends Command
{
    public function handle(ClientFiles $files, ClientDatabase $database): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->components->error('edunexus:client:drop only runs in local and testing environments.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->components->error('This permanently deletes the client\'s data. Re-run with --force to confirm.');

            return self::FAILURE;
        }

        try {
            $client = $files->read((string) $this->argument('code'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $database->drop($client);
        $files->delete($client->code);

        $this->components->info("Dropped client [{$client->code}]: database and user {$client->database}, and its deployment files.");

        return self::SUCCESS;
    }
}
