<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Row-level tenant isolation.
 *
 *  - Every query is filtered to the current company (see App\Support\Tenancy).
 *    That covers index pages, reports, exports AND route-model binding, so
 *    /employees/15 returns 404 when employee 15 belongs to another company.
 *  - company_id is filled automatically on create, either from the current
 *    tenant or — for background work — inferred from the parent record
 *    (employee_id, payroll_id, loan_id, payroll_run_id).
 *
 * Escape hatch for platform tooling: Model::withoutGlobalScope('company')
 * or Tenancy::withoutScope(fn () => ...).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (Tenancy::scopeDisabled()) {
                return;
            }

            $companyId = Tenancy::companyId();

            if ($companyId) {
                $builder->where($builder->getModel()->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function (Model $model) {
            if (! empty($model->company_id)) {
                return;
            }

            $model->company_id = Tenancy::companyId() ?? $model->inferCompanyIdFromParent();
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where($this->qualifyColumn('company_id'), $companyId);
    }

    /**
     * Look the company up through the parent row when no tenant context exists
     * (e.g. a queued job or artisan command creating child records).
     */
    protected function inferCompanyIdFromParent(): ?int
    {
        $parents = [
            'employee_id'    => 'employees',
            'payroll_id'     => 'payrolls',
            'loan_id'        => 'loans',
            'payroll_run_id' => 'payroll_runs',
        ];

        foreach ($parents as $column => $table) {
            $id = $this->getAttribute($column);

            if ($id) {
                $companyId = DB::connection($this->getConnectionName())
                    ->table($table)->where('id', $id)->value('company_id');

                if ($companyId) {
                    return (int) $companyId;
                }
            }
        }

        return null;
    }
}
