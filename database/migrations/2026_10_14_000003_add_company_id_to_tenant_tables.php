<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level multi-tenancy: every table that holds company data gets a
 * company_id, existing rows are back-filled from their parent record, and
 * per-company uniqueness replaces the old global unique indexes.
 */
return new class extends Migration
{
    /** table => how to back-fill company_id */
    private array $tables = [
        'payroll_runs'            => 'special',
        'payrolls'                => 'employees',
        'payroll_items'           => 'payrolls',
        'payroll_rules'           => 'fallback',
        'payroll_assignments'     => 'employees',
        'payroll_run_adjustments' => 'employees',
        'attendances'             => 'employees',
        'holidays'                => 'fallback',
        'leave_requests'          => 'employees',
        'leave_records'           => 'employees',
        'overtime_requests'       => 'employees',
        'loan_requests'           => 'employees',
        'loans'                   => 'employees',
        'loan_payments'           => 'loans',
    ];

    public function up(): void
    {
        // 1. Add the column + index everywhere
        foreach (array_keys($this->tables) as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            });
        }

        $fallbackCompanyId = $this->fallbackCompanyId();

        // Employees created before company_id was enforced
        if ($fallbackCompanyId) {
            DB::table('employees')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        }

        // 2. Back-fill from parents
        foreach ($this->tables as $table => $source) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            match ($source) {
                'employees' => DB::statement("UPDATE `{$table}` t JOIN `employees` e ON e.id = t.employee_id SET t.company_id = e.company_id WHERE t.company_id IS NULL"),
                'payrolls'  => DB::statement("UPDATE `{$table}` t JOIN `payrolls` p ON p.id = t.payroll_id SET t.company_id = p.company_id WHERE t.company_id IS NULL"),
                'loans'     => DB::statement("UPDATE `{$table}` t JOIN `loans` l ON l.id = t.loan_id SET t.company_id = l.company_id WHERE t.company_id IS NULL"),
                default     => null,
            };
        }

        // payroll_runs: take the company of the employees on the run, then the creator's company
        DB::statement("UPDATE `payroll_runs` r JOIN (SELECT payroll_run_id, MIN(company_id) AS cid FROM payrolls WHERE company_id IS NOT NULL GROUP BY payroll_run_id) x ON x.payroll_run_id = r.id SET r.company_id = x.cid WHERE r.company_id IS NULL");
        if (Schema::hasColumn('payroll_runs', 'created_by')) {
            DB::statement("UPDATE `payroll_runs` r JOIN `users` u ON u.id = r.created_by SET r.company_id = u.company_id WHERE r.company_id IS NULL");
        }

        // Anything still orphaned belongs to the original (first) company
        if ($fallbackCompanyId) {
            foreach (array_keys($this->tables) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
                }
            }
        }

        // A brand-new database (fresh install or a new dedicated workspace) has no
        // company yet: drop the legacy global D_LOAN rule — every company gets its own.
        if (! $fallbackCompanyId) {
            DB::table('payroll_rules')->whereNull('company_id')->delete();
        }

        // 3. Per-company uniqueness for the employee number (EIN)
        $this->dropIndexIfExists('employees', 'employees_employee_id_unique');
        if (! $this->indexExists('employees', 'employees_company_id_employee_id_unique')) {
            Schema::table('employees', function (Blueprint $t) {
                $t->unique(['company_id', 'employee_id']);
            });
        }

        // 4. Every company needs its own payroll rule set. Companies that existed
        //    before multi-tenancy (other than the original one) get the defaults.
        if (class_exists(\App\Services\DefaultPayrollRules::class)) {
            foreach (DB::table('companies')->pluck('id') as $companyId) {
                \App\Services\DefaultPayrollRules::ensureFor((int) $companyId);
            }
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('employees', 'employees_company_id_employee_id_unique');

        foreach (array_keys($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex([ 'company_id' ]);
                    $t->dropColumn('company_id');
                });
            }
        }
    }

    /**
     * The company that owned all data before multi-tenancy: the one with the
     * most employees, otherwise the oldest.
     */
    private function fallbackCompanyId(): ?int
    {
        $top = DB::table('employees')->whereNotNull('company_id')
            ->select('company_id', DB::raw('COUNT(*) AS c'))
            ->groupBy('company_id')->orderByDesc('c')->value('company_id');

        return $top ? (int) $top : (DB::table('companies')->min('id') ?: null);
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))->contains(fn ($i) => $i->Key_name === $index);
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
