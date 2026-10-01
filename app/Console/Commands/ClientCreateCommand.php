<?php

namespace App\Console\Commands;

use App\Support\Deployment\CreateClient;
use App\Support\Deployment\InstitutionType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('edunexus:client:create
    {code : Client code: 2-20 lowercase letters or digits, e.g. "abc"}
    {--name= : Institution name, e.g. "ABC Public School"}
    {--type= : Institution type: school, college or coaching}
    {--admin-email= : Super Admin email}
    {--admin-name=Super Admin : Super Admin name}
    {--url= : APP_URL for the client (defaults to EDUNEXUS_CLIENT_URL)}
    {--ask-password : Prompt for the Super Admin password instead of generating one}')]
#[Description('Create a client: deployment .env files, its own database and MySQL user, schema, institution-type data and a Super Admin')]
class ClientCreateCommand extends Command
{
    public function handle(CreateClient $createClient): int
    {
        $types = array_column(InstitutionType::cases(), 'value');

        $name = $this->option('name') ?? $this->ask('Institution name');
        $type = $this->option('type') ?? $this->choice('Institution type', $types, 0);
        $adminEmail = $this->option('admin-email') ?? $this->ask('Super Admin email');
        $adminPassword = $this->option('ask-password') ? $this->askForPassword() : null;

        if ($adminPassword === false) {
            return self::FAILURE;
        }

        try {
            $result = $createClient->handle(
                code: (string) $this->argument('code'),
                name: (string) $name,
                type: (string) $type,
                adminEmail: (string) $adminEmail,
                adminName: $this->option('admin-name'),
                adminPassword: $adminPassword,
                url: $this->option('url'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $client = $result['client'];

        $this->components->info("Client [{$client->code}] created.");
        $this->components->twoColumnDetail('Client code', $client->code);
        $this->components->twoColumnDetail('Institution', "{$client->name} ({$client->type->label()})");
        $this->components->twoColumnDetail('Database', $client->database);
        $this->components->twoColumnDetail('Login URL', $client->url.'/login');
        $this->components->twoColumnDetail('Super Admin', $result['admin_email']);

        if ($adminPassword === null) {
            $this->components->twoColumnDetail('Password', $result['admin_password']);
            $this->components->warn('This password is shown only once. Store it safely.');
        }

        $this->newLine();
        $this->line("  Run as this client: <comment>EDUNEXUS_CLIENT={$client->code} php artisan serve</comment>");

        return self::SUCCESS;
    }

    private function askForPassword(): string|false
    {
        $password = (string) $this->secret('Super Admin password (min. 8 characters)');

        if (strlen($password) < 8) {
            $this->components->error('The password must be at least 8 characters.');

            return false;
        }

        if ($password !== $this->secret('Confirm password')) {
            $this->components->error('The passwords do not match.');

            return false;
        }

        return $password;
    }
}
