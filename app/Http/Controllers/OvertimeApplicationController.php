<?php

namespace App\Http\Controllers;

use App\Mail\OvertimeApplied;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OvertimeApplicationController extends Controller
{
    public function form()
    {
        return view('overtime.apply');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email',
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'type'       => 'required|string|max:40',
            'comment'    => 'nullable|string|max:1000',
        ]);

        $employee = Employee::where('employment_status', 'Active')
            ->where('company_email', $validated['email'])
            ->first();

        if (! $employee) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This email address doesn\'t match any employee record. Please use your registered company email address.']);
        }

        // Everything below (holidays, notifications...) is scoped to the employee's company.
        \App\Support\Tenancy::setCompanyId((int) $employee->company_id);

        // Same rules as the portal: breaks, overnight shifts, company rates, overlaps, approvers.
        $overtimeRequest = app(\App\Services\Overtime\OvertimeService::class)->apply($employee, [
            'date' => $validated['date'], 'start_time' => $validated['start_time'], 'end_time' => $validated['end_time'],
            'break_minutes' => (int) $request->input('break_minutes', 0), 'type' => $validated['type'],
            'comment' => $validated['comment'] ?? null, 'company_email' => $validated['email'],
        ]);

        return view('overtime.applied', ['overtimeRequest' => $overtimeRequest]);
    }

    private function notifyHr(OvertimeRequest $overtimeRequest): void
    {
        $employee = $overtimeRequest->employee;

        $adminEmails = User::where('company_id', $employee->company_id)
            ->where('role', 'company_admin')
            ->pluck('email')
            ->toArray();

        if (empty($adminEmails)) {
            return;
        }

        try {
            Mail::to($adminEmails)->send(new OvertimeApplied($overtimeRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
