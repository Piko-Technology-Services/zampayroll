<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Console\Command;

class TenancyProvision extends Command
{
    protected $signature = 'tenancy:provision
        {company : Company id or sub-domain}
        {--database= : Use an existing (pre-created) database instead of creating zp_{subdomain}}';

    protected $description = 'Create / migrate the dedicated database of a workspace and activate it';

    public function handle(TenantProvisioner $provisioner): int
    {
        $key = $this->argument('company');

        $company = Company::on(Tenancy::landlordConnection())
            ->where(is_numeric($key) ? 'id' : 'subdomain', $key)
            ->first();

        if (! $company) {
            $this->error("No company found for [{$key}].");

            return self::FAILURE;
        }

        $this->info("Provisioning {$company->name} ({$company->subdomain})…");

        $company = $provisioner->provision($company, $this->option('database'));

        $this->info("Done. Workspace is live at {$company->workspaceUrl('/login')} using database {$company->database_name}.");

        return self::SUCCESS;
    }
}
