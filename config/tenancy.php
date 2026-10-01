<?php

declare(strict_types=1);

use App\Central\Tenant\Models\Domain;
use App\Central\Tenant\Models\Tenant;
use App\Support\Tenancy\PrefixCacheTenancyBootstrapper;
use App\Support\Tenancy\TenantContextTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\UUIDGenerator;

return [

    'tenant_model' => Tenant::class,

    'id_generator' => UUIDGenerator::class,

    'domain_model' => Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | Hosts that serve the platform itself (school lookup, signup, platform
    | admin). Tenant routes are never reachable on these hosts.
    |
    */

    'central_domains' => array_values(array_filter(explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1')))),

    /*
    |--------------------------------------------------------------------------
    | Tenancy Bootstrappers
    |--------------------------------------------------------------------------
    |
    | Run, in order, every time tenancy is initialized for a request, job or
    | command, and reverted when tenancy ends.
    |
    */

    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        PrefixCacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
        TenantContextTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        /**
         * Connection used as a template for the dynamically created tenant
         * connection. Null means the central connection is used, so one app
         * DB user with grants on every school database.
         */
        'template_tenant_connection' => null,

        /**
         * Tenant databases are named "{prefix}{code}", e.g. "school_abc".
         */
        'prefix' => env('TENANCY_DB_PREFIX', 'school_'),
        'suffix' => '',

        'managers' => [
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        /**
         * Cache stores that stay unprefixed in tenant context. Platform data
         * (domain lookups, entitlements) lives here so the central side can
         * read and invalidate it.
         */
        'central_store' => 'central',
    ],

    'filesystem' => [
        /**
         * Paths become "tenants/{tenant_id}/..." on every listed disk.
         */
        'suffix_base' => 'tenants/',
        'disks' => [
            'local',
            'public',
            's3',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [],

    /**
     * Tenant routes are registered by AppServiceProvider, not by the package.
     */
    'routes' => false,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\Seeders\TenantDatabaseSeeder',
    ],
];
