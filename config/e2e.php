<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Playwright Fixture Admin
    |--------------------------------------------------------------------------
    |
    | Credentials for the admin user the e2e fixture creates
    | (database/seeders/E2ESeeder). Read through config() rather than env()
    | directly, because env() returns null once the configuration is cached.
    | Mirrored in e2e/helpers/accounts.ts — keep in sync.
    |
    */

    'admin_email' => env('E2E_ADMIN_EMAIL', 'admin@example.com'),

    'admin_password' => env('E2E_ADMIN_PASSWORD', 'password'),

];
