<?php

namespace App\Services;

use App\Models\Company;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Moves a company from the shared database to its own private workspace
 * (sub-domain + database), keeping every record and id:
 *
 *  1. create & migrate the new database
 *  2. copy the company's rows from every company table (ids preserved)
 *  3. check the row counts match
 *  4. only then delete them from the shared database and switch the company over
 *
 * ZamPayroll billing, support tickets and the audit trail stay in the central database.
 * If anything fails before step 4 the shared data is untouched and the new database is dropped.
 */
class TenantUpgrader
{
    /** Central tables that never move. */
    private const CENTRAL = ['companies', 'invoices', 'billing_payments', 'support_tickets', 'support_ticket_messages',
        'tenant_user_directory', 'platform_settings', 'audit_logs', 'sessions', 'password_reset_tokens', 'migrations',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'demo_requests', 'payments'];

    public function upgrade(Company $company, string $subdomain): Company
    {
        $landlord = Tenancy::landlordConnection();
        $company = Company::on($landlord)->findOrFail($company->id);

        if ($company->isDedicated()) {
            throw new RuntimeException('This company already has a private workspace.');
        }
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            throw new RuntimeException('Invalid sub-domain.');
        }
        if (Company::on($landlord)->where('subdomain', $subdomain)->where('id', '!=', $company->id)->exists()) {
            throw new RuntimeException("The sub-domain {$subdomain} is taken.");
        }

        $database = Tenancy::databaseNameFor($subdomain);
        $exists = DB::connection($landlord)->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]);
        if ($exists) {
            throw new RuntimeException("A database called {$database} already exists. Choose another sub-domain.");
        }

        DB::connection($landlord)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            Tenancy::configureTenantConnection($database);
            $tenant = Tenancy::tenantConnection();
            Artisan::call('migrate', ['--database' => $tenant, '--path' => 'database/migrations', '--force' => true]);

            $tables = $this->companyTables($landlord);
            $copied = [];

            DB::connection($tenant)->statement('SET FOREIGN_KEY_CHECKS=0');
            // company row first
            $row = (array) DB::connection($landlord)->table('companies')->where('id', $company->id)->first();
            $row = array_merge($row, ['tenancy_mode' => Company::MODE_DEDICATED, 'subdomain' => $subdomain, 'database_name' => $database, 'status' => Company::STATUS_ACTIVE]);
            DB::connection($tenant)->table('companies')->insert($row);

            foreach ($tables as $table) {
                if (! Schema::connection($tenant)->hasTable($table)) {
                    continue;
                }
                $tenantColumns = Schema::connection($tenant)->getColumnListing($table);
                $n = 0;
                DB::connection($landlord)->table($table)->where('company_id', $company->id)->orderBy('id')
                    ->chunk(500, function ($rows) use ($tenant, $table, $tenantColumns, &$n) {
                        $insert = $rows->map(fn ($r) => array_intersect_key((array) $r, array_flip($tenantColumns)))->all();
                        DB::connection($tenant)->table($table)->insert($insert);
                        $n += count($insert);
                    });
                $copied[$table] = $n;
            }
            DB::connection($tenant)->statement('SET FOREIGN_KEY_CHECKS=1');

            // Verify before touching the shared database
            foreach ($copied as $table => $n) {
                $source = DB::connection($landlord)->table($table)->where('company_id', $company->id)->count();
                if ($source !== $n) {
                    throw new RuntimeException("Copy check failed for {$table}: {$source} rows in shared, {$n} copied.");
                }
            }
        } catch (\Throwable $e) {
            DB::connection($landlord)->statement("DROP DATABASE IF EXISTS `{$database}`");
            Log::error("Upgrade of company {$company->id} failed: {$e->getMessage()}");

            throw $e;
        }

        // Switch over: remove from shared, point the company at its new home.
        $users = DB::connection($landlord)->table('users')->where('company_id', $company->id)->get(['id', 'email']);
        DB::connection($landlord)->transaction(function () use ($landlord, $company, $copied, $subdomain, $database, $users) {
            DB::connection($landlord)->statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (array_reverse(array_keys($copied)) as $table) {
                DB::connection($landlord)->table($table)->where('company_id', $company->id)->delete();
            }
            DB::connection($landlord)->statement('SET FOREIGN_KEY_CHECKS=1');
            DB::connection($landlord)->table('sessions')->whereIn('user_id', $users->pluck('id'))->delete();

            $company->forceFill([
                'tenancy_mode' => Company::MODE_DEDICATED, 'subdomain' => $subdomain, 'database_name' => $database,
                'status' => Company::STATUS_ACTIVE, 'provisioned_at' => now(), 'requested_subdomain' => null,
            ])->save();
        });

        foreach ($users as $u) {
            Tenancy::syncDirectory(strtolower($u->email), (int) $company->id, (int) $u->id);
        }

        Audit::record('workspace.upgraded', $company, "Moved to private workspace {$subdomain} ({$database})", ['rows' => $copied], $company->id);
        Log::info("Company {$company->id} upgraded to {$subdomain}", $copied);

        return $company->fresh();
    }

    /** Every shared table with a company_id column (except central ones). */
    private function companyTables(string $connection): array
    {
        $db = DB::connection($connection)->getDatabaseName();

        return collect(DB::connection($connection)->select(
            'SELECT TABLE_NAME AS t FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?', [$db, 'company_id']
        ))->pluck('t')->reject(fn ($t) => in_array($t, self::CENTRAL, true))->unique()->values()->all();
    }
}
