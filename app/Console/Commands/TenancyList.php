<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Console\Command;

class TenancyList extends Command
{
    protected $signature = 'tenancy:list';

    protected $description = 'List companies with their tenancy mode, workspace and status';

    public function handle(): int
    {
        $rows = Company::on(Tenancy::landlordConnection())->orderBy('id')->get()->map(fn (Company $c) => [
            $c->id,
            $c->name,
            $c->tenancy_mode,
            $c->employee_count_range ?? '—',
            $c->isDedicated() ? $c->workspaceHost() : Tenancy::centralDomain() . ' (shared)',
            $c->database_name ?? config('database.connections.' . Tenancy::landlordConnection() . '.database'),
            $c->status,
        ]);

        $this->table(['ID', 'Company', 'Mode', 'Employees', 'Workspace', 'Database', 'Status'], $rows);

        return self::SUCCESS;
    }
}
