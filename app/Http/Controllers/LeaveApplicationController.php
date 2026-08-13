<?php

namespace App\Http\Controllers;

use App\Mail\LeaveApplied;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeaveApplicationController extends Controller
{
    public function form()
    {
        return view('leave.apply');
    }

    /**
     * Public lookup used by the form's JS to live-calculate Return Date and
     * Days Taken. Only returns work-day/holiday config — never employee data
     * — and only once the email matches an active employee record.
     */
    public function workDaysLookup(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $employee = Employee::where('employment_status', 'Active')
            ->where('company_email', $request->query('email'))
            ->first();

        if (! $employee) {
            return response()->json(['matched' => false]);
        }

        $workDays = array_map('intval', $employee->company?->work_days ?: [1, 2, 3, 4, 5]);

        $holidays = Holiday::all()->map(fn ($h) => [
            'date'         => $h->date->format('Y-m-d'),
            'is_recurring' => $h->is_recurring,
        ])->values();

        return response()->json([
            'matched'   => true,
            'work_days' => $workDays,
            'holidays'  => $holidays,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email',
            'leave_type'       => 'required|in:' . implode(',', array_keys(LeaveRequest::TYPES)),
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'return_date'      => 'nullable|date|after_or_equal:end_date',
            'comment'          => 'nullable|string|max:1000',
            'supervisor_email' => 'nullable|email',
            'documents.*'      => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        // ── Identity check: the email must match a company_email already
        //    on file for an active employee. This is the only gate — no
        //    login required, but a stranger can't submit for someone else.
        $employee = Employee::where('employment_status', 'Active')
            ->where('company_email', $validated['email'])
            ->first();

        if (! $employee) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This email address doesn\'t match any employee record. Please use your registered company email address.']);
        }

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        $days = $this->businessDays($start, $end, $employee);

        // Return date is auto-calculated server-side too (authoritative),
        // ignoring whatever the JS pre-filled — the client value is only a
        // convenience, never trusted for the saved record.
        $returnDate = $this->nextBusinessDay($end, $employee);

        // ── Supporting documents (optional, multiple)
        $documents = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('uploads/leave/documents', 'public');
                $documents[] = [
                    'name'        => $file->getClientOriginalName(),
                    'path'        => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id'      => $employee->id,
            'leave_type'       => $validated['leave_type'],
            'company_email'    => $validated['email'],
            'start_date'       => $validated['start_date'],
            'end_date'         => $validated['end_date'],
            'return_date'      => $returnDate,
            'days'             => $days,
            'reason'           => $validated['comment'] ?? null,
            'supervisor_email' => $validated['supervisor_email'] ?? null,
            'documents'        => $documents ?: null,
            'status'           => 'pending',
        ]);

        $this->notifyHr($leaveRequest, $validated['supervisor_email'] ?? null);

        return view('leave.applied', ['leaveRequest' => $leaveRequest]);
    }

    private function notifyHr(LeaveRequest $leaveRequest, ?string $supervisorEmail): void
    {
        $employee = $leaveRequest->employee;

        $adminEmails = User::where('company_id', $employee->company_id)
            ->where('role', 'company_admin')
            ->pluck('email')
            ->toArray();

        if (empty($adminEmails)) {
            return;
        }

        try {
            $mail = Mail::to($adminEmails);

            if ($supervisorEmail) {
                $mail->cc($supervisorEmail);
            }

            $mail->send(new LeaveApplied($leaveRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Business days between two dates (inclusive), excluding the company's
     * non-working weekdays and public holidays — same logic as attendance.
     */
    private function businessDays(Carbon $start, Carbon $end, Employee $employee): float
    {
        $workDays = $this->companyWorkDays($employee);
        $holidays = Holiday::all();

        $days = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($this->isWorkingDay($cursor, $workDays, $holidays)) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * First working day strictly after $end — the auto-calculated return date.
     */
    private function nextBusinessDay(Carbon $end, Employee $employee): Carbon
    {
        $workDays = $this->companyWorkDays($employee);
        $holidays = Holiday::all();

        $cursor = $end->copy()->addDay();

        while (! $this->isWorkingDay($cursor, $workDays, $holidays)) {
            $cursor->addDay();
        }

        return $cursor;
    }

    private function isWorkingDay(Carbon $date, array $workDays, $holidays): bool
    {
        $isHoliday = $holidays->contains(fn ($h) => $h->is_recurring
            ? $h->date->format('m-d') === $date->format('m-d')
            : $h->date->format('Y-m-d') === $date->format('Y-m-d'));

        return in_array($date->dayOfWeekIso, $workDays, true) && ! $isHoliday;
    }

    private function companyWorkDays(Employee $employee): array
    {
        return array_map('intval', $employee->company?->work_days ?: [1, 2, 3, 4, 5]);
    }
}