<?php

namespace App\Console\Commands;

use App\Support\Deployment\Client;
use App\Support\Deployment\ClientFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('edunexus:client:list')]
#[Description('List clients found in the deployments directory')]
class ClientListCommand extends Command
{
    public function handle(ClientFiles $files): int
    {
        $clients = $files->all();

        if ($clients === []) {
            $this->components->info("No clients in [{$files->root()}]. Create one with edunexus:client:create.");

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Name', 'Type', 'Database', 'URL'],
            array_map(fn (Client $client): array => [$client->code, $client->name, $client->type->value, $client->database, $client->url], $clients),
        );

        return self::SUCCESS;
    }
}
