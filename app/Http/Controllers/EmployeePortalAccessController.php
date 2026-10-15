<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeePortalService;
use Illuminate\Http\Request;

/**
 * HR side of the self-service portal: create, re-send and revoke access.
 */
class EmployeePortalAccessController extends Controller
{
    public function __construct(private EmployeePortalService $portal)
    {
    }

    public function store(Request $request, Employee $employee)
    {
        if ($problem = $this->portal->eligibilityProblem($employee)) {
            return back()->with('error', "Can't create a portal for {$employee->full_name}: they {$problem}.");
        }

        [$user] = $this->portal->grant($employee, $request->user());

        return back()->with('success', "🎉 Portal created for {$employee->full_name}. Login details and instructions were emailed to {$user->email}.");
    }

    public function resend(Request $request, Employee $employee)
    {
        if (! $employee->user) {
            return back()->with('error', "{$employee->full_name} doesn't have portal access yet.");
        }

        $user = $this->portal->resend($employee, $request->user());

        return back()->with('success', "New temporary password sent to {$user->email}.");
    }

    public function destroy(Employee $employee)
    {
        $this->portal->revoke($employee);

        return back()->with('success', "Portal access removed for {$employee->full_name}.");
    }

    /**
     * Create portals for the selected employees (or everyone eligible).
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer',
        ]);

        $query = Employee::query()->doesntHave('user');

        if ($ids = $request->input('employee_ids')) {
            $query->whereIn('id', $ids);
        }

        $created = 0;
        $skipped = [];

        foreach ($query->get() as $employee) {
            if ($problem = $this->portal->eligibilityProblem($employee)) {
                $skipped[] = "{$employee->full_name} ({$problem})";
                continue;
            }

            $this->portal->grant($employee, $request->user());
            $created++;
        }

        $message = $created
            ? "🎉 {$created} portal" . ($created === 1 ? '' : 's') . ' created — login details have been emailed.'
            : 'No new portals were created.';

        if ($skipped) {
            $message .= ' Skipped: ' . implode('; ', array_slice($skipped, 0, 8)) . (count($skipped) > 8 ? '; …' : '') . '.';
        }

        return back()->with($created ? 'success' : 'error', $message);
    }
}
