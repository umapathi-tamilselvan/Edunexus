<?php

$csv = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    |
    | The institution this deployment serves. Written to the client's
    | deployments/<code>/.env by educore:client:create. The root .env acts
    | as the development client.
    |
    */

    'client' => [
        'code' => env('EDUCORE_CLIENT_CODE', 'dev'),
        'name' => env('EDUCORE_ORG_NAME', 'EduCore Development'),
        'type' => env('EDUCORE_INSTITUTION_TYPE', 'school'),
    ],

    'receipt_prefix' => env('RECEIPT_PREFIX', 'RCPT'),

    /*
    |--------------------------------------------------------------------------
    | Module Overrides
    |--------------------------------------------------------------------------
    |
    | Comma-separated module codes added to or removed from the institution
    | type's defaults, e.g. EDUCORE_MODULES_ENABLE=hostel,library.
    |
    */

    'modules' => [
        'enable' => $csv('EDUCORE_MODULES_ENABLE'),
        'disable' => $csv('EDUCORE_MODULES_DISABLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Provisioning
    |--------------------------------------------------------------------------
    |
    | Used by the educore:client:* commands. Each client gets database and
    | MySQL user "{prefix}{code}", with rights on that database only.
    |
    */

    'provisioning' => [
        'deployments_path' => env('EDUCORE_DEPLOYMENTS_PATH', base_path('deployments')),
        'database_prefix' => env('EDUCORE_CLIENT_DB_PREFIX', 'educore_'),
        'user_host' => env('DB_PROVISION_USER_HOST', '%'),
        'default_url' => env('EDUCORE_CLIENT_URL', 'http://localhost:8000'),
    ],

];
