<?php

namespace App\Support\Deployment;

/**
 * One client deployment, as described by deployments/<code>/.env and
 * deployments/<code>/.env.secure.
 */
final readonly class Client
{
    public function __construct(
        public string $code,
        public string $name,
        public InstitutionType $type,
        public string $url,
        public string $database,
        public string $username,
        #[\SensitiveParameter] public string $password,
    ) {}
}
