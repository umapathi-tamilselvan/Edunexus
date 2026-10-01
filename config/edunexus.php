<?php

$csv = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    |
    | The institution this deployment serves. Written to the client's
    | deployments/<code>/.env by edunexus:client:create. The root .env acts
    | as the development client.
    |
    */

    'client' => [
        'code' => env('EDUNEXUS_CLIENT_CODE', 'dev'),
        'name' => env('EDUNEXUS_ORG_NAME', 'EduNexus Development'),
        'type' => env('EDUNEXUS_INSTITUTION_TYPE', 'school'),
    ],

    'receipt_prefix' => env('RECEIPT_PREFIX', 'RCPT'),

    /*
    |--------------------------------------------------------------------------
    | Privacy Notice
    |--------------------------------------------------------------------------
    |
    | Version of the privacy notice shown to guardians. Stored with every
    | consent record (DPDP Act); bump it whenever the notice text changes.
    |
    */

    'privacy_notice_version' => env('EDUNEXUS_PRIVACY_NOTICE_VERSION', '2026-10'),

    /*
    |--------------------------------------------------------------------------
    | Module Overrides
    |--------------------------------------------------------------------------
    |
    | Comma-separated module codes added to or removed from the institution
    | type's defaults, e.g. EDUNEXUS_MODULES_ENABLE=hostel,library.
    |
    */

    'modules' => [
        'enable' => $csv('EDUNEXUS_MODULES_ENABLE'),
        'disable' => $csv('EDUNEXUS_MODULES_DISABLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Provisioning
    |--------------------------------------------------------------------------
    |
    | Used by the edunexus:client:* commands. Each client gets database and
    | MySQL user "{prefix}{code}", with rights on that database only.
    |
    */

    'provisioning' => [
        'deployments_path' => env('EDUNEXUS_DEPLOYMENTS_PATH', base_path('deployments')),
        'database_prefix' => env('EDUNEXUS_CLIENT_DB_PREFIX', 'edunexus_'),
        'user_host' => env('DB_PROVISION_USER_HOST', '%'),
        'default_url' => env('EDUNEXUS_CLIENT_URL', 'http://localhost:8000'),
    ],

];
