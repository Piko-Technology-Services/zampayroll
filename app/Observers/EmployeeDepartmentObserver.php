<?php

namespace App\Observers;

use App\Models\Department;
use App\Models\Employee;

/** Any department name given to an employee (form, import, AI, API) appears in the managed list. */
class EmployeeDepartmentObserver
{
    public function saved(Employee $employee): void
    {
        if (($employee->wasRecentlyCreated || $employee->wasChanged('department')) && filled($employee->department)) {
            try {
                Department::ensure($employee->department, (int) $employee->company_id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
