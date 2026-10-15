<?php

namespace App\Console\Commands;

use App\Services\TenantProvisioner;
use Illuminate\Console\Command;

class TenancyMigrate extends Command
{
    protected $signature = 'tenancy:migrate';

    protected $description = 'Run pending migrations on every dedicated workspace database (run after php artisan migrate)';

    public function handle(TenantProvisioner $provisioner): int
    {
        $provisioner->migrateAll(function ($company, $output) {
            $this->info("== {$company->subdomain} ({$company->database_name})");
            $this->line(trim($output) ?: 'Nothing to migrate.');
        });

        return self::SUCCESS;
    }
}
