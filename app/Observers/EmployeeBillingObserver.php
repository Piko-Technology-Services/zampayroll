<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\Employee;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;

/**
 * Free plan ↔ trial: when the number of active employees changes, check whether
 * the company just went over (or back under) the free plan's limit.
 */
class EmployeeBillingObserver
{
    public function saved(Employee $employee): void
    {
        if ($employee->wasRecentlyCreated || $employee->wasChanged(['employment_status', 'employment_nature'])) {
            $this->sync($employee);
        }
    }

    public function deleted(Employee $employee): void
    {
        $this->sync($employee);
    }

    private function sync(Employee $employee): void
    {
        if (! $employee->company_id) {
            return;
        }

        try {
            $company = Company::on(Tenancy::landlordConnection())->find($employee->company_id);
            if ($company && ! in_array($company->subscription_status, ['active', 'past_due', 'cancelled'], true)) {
                app(Subscriptions::class)->syncFreeTier($company);
            }
        } catch (\Throwable $e) {
            report($e); // billing bookkeeping must never stop HR work; the daily run catches up
        }
    }
}
