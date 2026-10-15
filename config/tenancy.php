<?php

/*
|--------------------------------------------------------------------------
| ZamPayroll multi-tenancy
|--------------------------------------------------------------------------
|
| Two tenancy modes live side by side:
|
|   shared     Companies with up to `shared_employee_limit` employees. All of
|              their data lives in the main (landlord) database and is isolated
|              row-by-row with a `company_id` column + a global Eloquent scope
|              (App\Models\Concerns\BelongsToCompany).
|
|   dedicated  Companies with more employees get their own sub-domain
|              (acme.zampayroll.com) and their own MySQL database. The
|              IdentifyTenant middleware reads the sub-domain and switches the
|              default DB connection before the session even starts.
|
*/

return [

    // The host that serves the main site + shared tenants, e.g. "zampayroll.com"
    // (locally "localhost"). Dedicated tenants live on "{subdomain}.{central_domain}".
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    // Optional full root URL used to build tenant links outside an HTTP request
    // (queue workers, artisan). Scheme, port and path are kept, only the host
    // changes: http://localhost:8000 -> http://acme.localhost:8000
    'central_url' => env('TENANCY_CENTRAL_URL', env('APP_URL', 'http://localhost')),

    // Companies declaring MORE than this many employees get a dedicated workspace.
    'shared_employee_limit' => (int) env('TENANCY_SHARED_EMPLOYEE_LIMIT', 10),

    // The connection that always points at the main database.
    'landlord_connection' => env('DB_CONNECTION', 'mysql'),

    // The connection that is re-pointed at a dedicated tenant's database.
    'tenant_connection' => 'tenant',

    // Dedicated databases are named "{prefix}{subdomain}" (dashes become underscores).
    'database_prefix' => env('TENANCY_DB_PREFIX', 'zp_'),

    // When false, verified dedicated sign-ups wait in status "awaiting_database"
    // until an operator runs:  php artisan tenancy:provision {company} --database=...
    // (needed on shared hosting such as Hostinger where SQL users can't CREATE DATABASE).
    'auto_provision' => filter_var(env('TENANCY_AUTO_PROVISION', true), FILTER_VALIDATE_BOOL),

    // Sub-domains nobody may claim.
    'reserved_subdomains' => [
        'www', 'app', 'api', 'admin', 'mail', 'smtp', 'ftp', 'misc', 'portal', 'support',
        'help', 'static', 'cdn', 'assets', 'demo', 'billing', 'status', 'blog', 'docs',
        'dashboard', 'login', 'register', 'zampayroll', 'test', 'dev', 'staging',
    ],

    // Options offered by the registration wizard. Keys are stored on companies.employee_count_range.
    'employee_ranges' => [
        '1-5'     => ['label' => '1 – 5',     'title' => 'Just getting started', 'emoji' => '🌱', 'max' => 5],
        '6-10'    => ['label' => '6 – 10',    'title' => 'Small & mighty',       'emoji' => '🚀', 'max' => 10],
        '11-50'   => ['label' => '11 – 50',   'title' => 'Growing fast',         'emoji' => '📈', 'max' => 50],
        '51-200'  => ['label' => '51 – 200',  'title' => 'Established',          'emoji' => '🏢', 'max' => 200],
        '201-500' => ['label' => '201 – 500', 'title' => 'Big league',           'emoji' => '🏟️', 'max' => 500],
        '500+'    => ['label' => '500+',      'title' => 'Enterprise',           'emoji' => '🌍', 'max' => 100000],
    ],
];
