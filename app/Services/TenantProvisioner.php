<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Creates and fills the database of a dedicated (>10 employees) workspace.
 *
 *  1. CREATE DATABASE zp_{subdomain}  (skipped when the DB already exists)
 *  2. run every migration against it
 *  3. copy the company row (same id) + the admin account(s)
 *  4. seed the company's payroll rules
 *  5. record the users in the landlord e-mail directory, remove them from
 *     the landlord users table and mark the company active
 */
class TenantProvisioner
{
    public function provision(Company $company, ?string $database = null): Company
    {
        $landlord = Tenancy::landlordConnection();
        $company = Company::on($landlord)->findOrFail($company->id);

        if (! $company->isDedicated() || ! $company->subdomain) {
            throw new RuntimeException("Company {$company->id} is not a dedicated workspace.");
        }

        $database = $database ?: ($company->database_name ?: Tenancy::databaseNameFor($company->subdomain));

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException("Invalid database name [{$database}].");
        }

        $company->forceFill(['database_name' => $database, 'status' => Company::STATUS_PROVISIONING])->save();

        try {
            DB::connection($landlord)->statement(
                "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            Tenancy::configureTenantConnection($database);
            $tenant = Tenancy::tenantConnection();

            Artisan::call('migrate', [
                '--database' => $tenant,
                '--path'     => 'database/migrations',
                '--force'    => true,
            ]);

            $this->copyCompany($company, $tenant);
            $users = $this->moveUsers($company, $landlord, $tenant);

            // Payroll rules + anything else that has to exist inside the tenant DB.
            $this->insideTenant($company, function () use ($company) {
                DefaultPayrollRules::ensureFor((int) $company->id);
            });

            foreach ($users as $user) {
                Tenancy::syncDirectory($user->email, (int) $company->id, (int) $user->id);
            }

            $company->forceFill([
                'status'         => Company::STATUS_ACTIVE,
                'provisioned_at' => now(),
            ])->save();

            Log::info("Tenancy: provisioned dedicated workspace {$company->subdomain} ({$database})");
        } catch (\Throwable $e) {
            $company->forceFill(['status' => Company::STATUS_FAILED])->save();
            Log::error("Tenancy: provisioning {$company->subdomain} failed — {$e->getMessage()}");

            throw $e;
        }

        return $company->fresh();
    }

    /**
     * Run migrations for every active dedicated workspace (use after deploying new migrations).
     */
    public function migrateAll(?callable $output = null): void
    {
        Company::on(Tenancy::landlordConnection())
            ->where('tenancy_mode', Company::MODE_DEDICATED)
            ->whereNotNull('database_name')
            ->each(function (Company $company) use ($output) {
                Tenancy::configureTenantConnection($company->database_name);
                Artisan::call('migrate', ['--database' => Tenancy::tenantConnection(), '--path' => 'database/migrations', '--force' => true]);
                $output && $output($company, Artisan::output());
            });
    }

    /**
     * Run a callback with the default connection pointed at the tenant DB.
     */
    public function insideTenant(Company $company, callable $callback)
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection(Tenancy::tenantConnection());

        try {
            return Tenancy::runAs((int) $company->id, $callback);
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    private function copyCompany(Company $company, string $tenant): void
    {
        $row = DB::connection(Tenancy::landlordConnection())->table('companies')->where('id', $company->id)->first();
        $row = (array) $row;
        $row['status'] = Company::STATUS_ACTIVE;

        DB::connection($tenant)->table('companies')->updateOrInsert(['id' => $row['id']], $row);
    }

    /**
     * Move the company's users out of the landlord DB into the tenant DB (ids preserved).
     *
     * @return \Illuminate\Support\Collection<object>
     */
    private function moveUsers(Company $company, string $landlord, string $tenant)
    {
        $users = DB::connection($landlord)->table('users')->where('company_id', $company->id)->get();

        foreach ($users as $user) {
            $row = (array) $user;
            $row['employee_id'] = null; // employee ids don't exist yet in the new DB

            DB::connection($tenant)->table('users')->updateOrInsert(['id' => $row['id']], $row);
        }

        DB::connection($landlord)->table('sessions')->whereIn('user_id', $users->pluck('id'))->delete();
        DB::connection($landlord)->table('users')->whereIn('id', $users->pluck('id'))->delete();

        return $users;
    }
}
