<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | Each company in the platform gets a dedicated MySQL database of its own
    | (database-per-tenant). Master data is provisioned into that database the
    | moment the company is created. Set TENANCY_ENABLED=false to keep a single
    | shared database (the company_id-row model) while migrating.
    |
    */

    'enabled' => env('TENANCY_ENABLED', true),

    'database_prefix' => env('TENANT_DB_PREFIX', 'accounting_tenant_'),
];