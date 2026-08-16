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
            'type'       => 'required|in:' . implode(',', array_keys(OvertimeRequest::TYPES)),
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

        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);

        if ($end->lte($start)) {
            return back()->withInput()->withErrors(['end_time' => 'End time must be after start time.']);
        }

        $hours = round($end->diffInMinutes($start) / 60, 2);

        $multiplier = OvertimeRequest::TYPES[$validated['type']]['multiplier'];
        $grossForOt = $employee->natural_gross_salary ?? $employee->salary ?? 0;
        $dailyRate = round($grossForOt / $employee->working_days_per_month, 2);
        // Normal OT = (DailyRate / 8) x 1.5 x Hours | Double OT = (DailyRate / 8) x 2 x Hours
        $amount = round(($dailyRate / 8) * $multiplier * $hours, 2);

        $overtimeRequest = OvertimeRequest::create([
            'employee_id'     => $employee->id,
            'company_email'   => $validated['email'],
            'date'            => $validated['date'],
            'start_time'      => $validated['start_time'],
            'end_time'        => $validated['end_time'],
            'hours'           => $hours,
            'type'            => $validated['type'],
            'rate_multiplier' => $multiplier,
            'daily_rate'      => $dailyRate,
            'amount'          => $amount,
            'comment'         => $validated['comment'] ?? null,
            'status'          => 'pending',
        ]);

        $this->notifyHr($overtimeRequest);

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
