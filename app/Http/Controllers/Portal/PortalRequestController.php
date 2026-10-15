<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\LeaveApplied;
use App\Mail\LoanApplied;
use App\Mail\OvertimeApplied;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\LeaveDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Employee self-service requests. These create exactly the same records as
 * the public forms, so they show up in the existing HR dashboards
 * (Leave / Overtime / Loan Requests) for approval.
 */
class PortalRequestController extends Controller
{
    /* ================================================================== */
    /*  LEAVE                                                               */
    /* ================================================================== */

    public function leave(Request $request)
    {
        $employee = $request->user()->employee;

        return view('portal.leave', [
            'employee' => $employee,
            'requests' => LeaveRequest::where('employee_id', $employee->id)->latest()->get(),
            'types'    => LeaveRequest::TYPES,
            'usedThisYear' => (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')->whereYear('start_date', now()->year)->sum('days'),
        ]);
    }

    /**
     * Live preview for the form: working days + return date.
     */
    public function calculateLeave(Request $request)
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);

        $calc = new LeaveDayCalculator($request->user()->employee);
        $start = Carbon::parse($request->query('start_date'));
        $end = Carbon::parse($request->query('end_date'));

        return response()->json([
            'days'        => $calc->businessDays($start, $end),
            'return_date' => $calc->nextBusinessDay($end)->format('D, d M Y'),
            'holidays'    => $calc->holidaysBetween($start, $end)->map(fn ($h) => $h->name)->values(),
        ]);
    }

    public function storeLeave(Request $request)
    {
        $employee = $request->user()->employee;

        $validated = $request->validate([
            'leave_type'       => 'required|in:' . implode(',', array_keys(LeaveRequest::TYPES)),
            'start_date'       => 'required|date|after_or_equal:' . now()->subDays(30)->toDateString(),
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string|max:1000',
            'supervisor_email' => 'nullable|email',
            'documents.*'      => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        $calc = new LeaveDayCalculator($employee);
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $days = $calc->businessDays($start, $end);

        if ($days <= 0) {
            return back()->withInput()->withErrors(['end_date' => 'Those dates fall entirely on non-working days or holidays.']);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['start_date' => 'You already have a leave request that overlaps these dates.']);
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id'      => $employee->id,
            'leave_type'       => $validated['leave_type'],
            'company_email'    => $request->user()->email,
            'start_date'       => $start,
            'end_date'         => $end,
            'return_date'      => $calc->nextBusinessDay($end),
            'days'             => $days,
            'reason'           => $validated['reason'] ?? null,
            'supervisor_email' => $validated['supervisor_email'] ?? null,
            'documents'        => $this->storeDocuments($request, 'uploads/leave/documents'),
            'status'           => 'pending',
        ]);

        $this->notifyHr($employee, new LeaveApplied($leaveRequest), $validated['supervisor_email'] ?? null);

        return redirect()->route('portal.leave')
            ->with('success', "🌴 Leave request sent! {$days} working day(s) — HR will let you know once it's reviewed.");
    }

    public function cancelLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $this->cancel($request, $leaveRequest);

        return back()->with('success', 'Leave request cancelled.');
    }

    /* ================================================================== */
    /*  OVERTIME                                                            */
    /* ================================================================== */

    public function overtime(Request $request)
    {
        $employee = $request->user()->employee;
        $dailyRate = round(((float) ($employee->salary ?? 0)) / max(1, $employee->working_days_per_month), 2);

        return view('portal.overtime', [
            'employee'  => $employee,
            'requests'  => OvertimeRequest::where('employee_id', $employee->id)->latest()->get(),
            'types'     => OvertimeRequest::TYPES,
            'hourlyRate' => round($dailyRate / 8, 2),
            'approvedThisMonth' => (float) OvertimeRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('amount'),
        ]);
    }

    public function storeOvertime(Request $request)
    {
        $employee = $request->user()->employee;

        $validated = $request->validate([
            'date'       => 'required|date|before_or_equal:' . now()->addDays(7)->toDateString(),
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
            'type'       => 'required|in:' . implode(',', array_keys(OvertimeRequest::TYPES)),
            'comment'    => 'nullable|string|max:1000',
        ]);

        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);

        if ($end->lte($start)) {
            return back()->withInput()->withErrors(['end_time' => 'End time must be after start time.']);
        }

        $hours = round($start->diffInMinutes($end) / 60, 2);
        $multiplier = OvertimeRequest::TYPES[$validated['type']]['multiplier'];
        $dailyRate = round(((float) ($employee->salary ?? 0)) / max(1, $employee->working_days_per_month), 2);
        // Same formula as the public form: (DailyRate / 8) x multiplier x hours
        $amount = round(($dailyRate / 8) * $multiplier * $hours, 2);

        $overtimeRequest = OvertimeRequest::create([
            'employee_id'     => $employee->id,
            'company_email'   => $request->user()->email,
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

        $this->notifyHr($employee, new OvertimeApplied($overtimeRequest));

        return redirect()->route('portal.overtime')
            ->with('success', "⏱️ Overtime logged: {$hours} hrs (≈ K" . number_format($amount, 2) . ') sent to HR for approval.');
    }

    public function cancelOvertime(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->cancel($request, $overtimeRequest);

        return back()->with('success', 'Overtime request cancelled.');
    }

    /* ================================================================== */
    /*  LOANS                                                               */
    /* ================================================================== */

    public function loans(Request $request)
    {
        $employee = $request->user()->employee;

        return view('portal.loans', [
            'employee' => $employee,
            'requests' => LoanRequest::where('employee_id', $employee->id)->latest()->get(),
            'loans'    => Loan::with(['payments' => fn ($q) => $q->latest()])->where('employee_id', $employee->id)->latest()->get(),
            'plans'    => LoanRequest::PAYMENT_PLANS,
        ]);
    }

    public function storeLoan(Request $request)
    {
        $employee = $request->user()->employee;

        $validated = $request->validate([
            'amount'             => 'required|numeric|min:1',
            'payment_plan'       => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'  => 'nullable|required_if:payment_plan,other|string|max:255',
            'installment_amount' => 'nullable|required_unless:payment_plan,once_off|numeric|min:0.01|lte:amount',
            'reason'             => 'nullable|string|max:1000',
            'documents.*'        => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ], [
            'installment_amount.required_unless' => 'Tell us how much should be deducted each time.',
            'installment_amount.lte' => 'The instalment can\'t be more than the loan amount.',
        ]);

        if (LoanRequest::where('employee_id', $employee->id)->where('status', 'pending')->exists()) {
            return back()->withInput()->withErrors(['amount' => 'You already have a loan request waiting for HR. Please wait for a decision first.']);
        }

        $loanRequest = LoanRequest::create([
            'employee_id'        => $employee->id,
            'company_email'      => $request->user()->email,
            'amount'             => $validated['amount'],
            'payment_plan'       => $validated['payment_plan'],
            'payment_plan_note'  => $validated['payment_plan_note'] ?? null,
            'installment_amount' => $validated['payment_plan'] === 'once_off' ? null : ($validated['installment_amount'] ?? null),
            'reason'             => $validated['reason'] ?? null,
            'documents'          => $this->storeDocuments($request, 'uploads/loans/documents'),
            'status'             => 'pending',
        ]);

        $this->notifyHr($employee, new LoanApplied($loanRequest));

        return redirect()->route('portal.loans')
            ->with('success', '💳 Loan request for K' . number_format((float) $validated['amount'], 2) . ' sent to HR.');
    }

    public function cancelLoan(Request $request, LoanRequest $loanRequest)
    {
        $this->cancel($request, $loanRequest);

        return back()->with('success', 'Loan request cancelled.');
    }

    /* ================================================================== */

    /**
     * Employees can withdraw their own requests while they are still pending.
     */
    private function cancel(Request $request, $model): void
    {
        abort_unless((int) $model->employee_id === (int) $request->user()->employee_id, 404);

        if ($model->status !== 'pending') {
            abort(422, 'Only pending requests can be cancelled.');
        }

        $model->delete();
    }

    private function storeDocuments(Request $request, string $folder): ?array
    {
        if (! $request->hasFile('documents')) {
            return null;
        }

        return collect($request->file('documents'))->map(fn ($file) => [
            'name'        => $file->getClientOriginalName(),
            'path'        => $file->store($folder, 'public'),
            'uploaded_at' => now()->toDateTimeString(),
        ])->all();
    }

    private function notifyHr(Employee $employee, $mailable, ?string $cc = null): void
    {
        $adminEmails = User::where('company_id', $employee->company_id)
            ->whereIn('role', ['company_admin', 'hr', 'manager'])
            ->pluck('email')->all();

        if (! $adminEmails) {
            return;
        }

        try {
            $mail = Mail::to($adminEmails);
            if ($cc) {
                $mail->cc($cc);
            }
            $mail->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
