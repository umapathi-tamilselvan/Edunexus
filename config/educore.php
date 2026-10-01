<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | Every school gets a free subdomain on this domain at signup, e.g.
    | "abc.educore.in". Locally "localhost" gives "abc.localhost".
    |
    */

    'base_domain' => env('EDUCORE_BASE_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Tenant URLs
    |--------------------------------------------------------------------------
    |
    | Links in mails, messages, PDFs and API responses are built from the
    | school's primary domain with this scheme and optional port, never
    | from APP_URL.
    |
    */

    'tenant_url_scheme' => env('EDUCORE_TENANT_URL_SCHEME', 'https'),

    'tenant_url_port' => env('EDUCORE_TENANT_URL_PORT'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    */

    'domain_cache_ttl' => 600,

    'entitlement_cache_ttl' => 600,

];
