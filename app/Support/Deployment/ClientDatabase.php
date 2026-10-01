<?php

namespace App\Support\Deployment;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

/**
 * Creates and drops a client's database and MySQL user through the
 * privileged "provisioning" connection, and runs code against a client
 * database as that client's own user.
 */
class ClientDatabase
{
    public const CONNECTION = 'client';

    public function __construct(private DatabaseManager $db) {}

    public function databaseExists(string $database): bool
    {
        return $this->provisioning()->selectOne('SELECT 1 AS found FROM information_schema.schemata WHERE schema_name = ?', [$database]) !== null;
    }

    public function userExists(string $username): bool
    {
        return $this->provisioning()->selectOne('SELECT 1 AS found FROM mysql.user WHERE user = ?', [$username]) !== null;
    }

    /**
     * Database plus a user with rights on that database only.
     */
    public function provision(Client $client): void
    {
        $connection = $this->provisioning();
        $account = $this->account($client->username);

        $connection->statement("CREATE DATABASE `{$client->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $connection->statement("CREATE USER {$account} IDENTIFIED BY ".$connection->getPdo()->quote($client->password));
        $connection->statement("GRANT ALL PRIVILEGES ON `{$client->database}`.* TO {$account}");
    }

    public function drop(Client $client): void
    {
        $connection = $this->provisioning();

        $connection->statement("DROP DATABASE IF EXISTS `{$client->database}`");
        $connection->statement('DROP USER IF EXISTS '.$this->account($client->username));
    }

    /**
     * Run a callback with the client's database as the default connection,
     * connected as the client's own (unprivileged) user.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(Client $client, callable $callback): mixed
    {
        $default = $this->db->getDefaultConnection();

        config(['database.connections.'.self::CONNECTION => [
            ...config('database.connections.mysql'),
            'database' => $client->database,
            'username' => $client->username,
            'password' => $client->password,
        ]]);
        $this->db->purge(self::CONNECTION);
        $this->db->setDefaultConnection(self::CONNECTION);

        try {
            return $callback();
        } finally {
            $this->db->setDefaultConnection($default);
            $this->db->purge(self::CONNECTION);
        }
    }

    private function provisioning(): Connection
    {
        return $this->db->connection('provisioning');
    }

    private function account(string $username): string
    {
        $host = (string) config('edunexus.provisioning.user_host');

        return "'{$username}'@'".str_replace("'", '', $host)."'";
    }
}
