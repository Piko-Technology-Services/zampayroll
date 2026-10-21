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
use App\Services\Leave\LeaveService;
use App\Services\Overtime\OvertimeService;
use App\Services\Loans\LoanService;
use App\Services\LeaveDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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

    public function leave(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;
        $types = $service->types($employee->company_id)->where('employees_can_apply', true)->values();

        return view('portal.leave', [
            'employee' => $employee,
            'requests' => LeaveRequest::where('employee_id', $employee->id)->latest()->get(),
            'types'    => $types->pluck('name', 'key')->all(),
            'typeInfo' => $types->keyBy('key'),
            'balance'  => $service->balance($employee),
            'usedThisYear' => $service->balance($employee)['taken_this_year'],
        ]);
    }

    /**
     * Live preview for the form: working days, return date, and whether the rules allow it.
     */
    public function calculateLeave(Request $request, LeaveService $service)
    {
        $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'leave_type' => 'nullable|string']);
        $employee = $request->user()->employee;

        try {
            $c = $service->check($employee, $request->query('leave_type', 'annual'), $request->query('start_date'), $request->query('end_date'), 1);

            return response()->json(['ok' => true, 'days' => $c['days'], 'return_date' => $c['return_date']->format('D, d M Y'), 'holidays' => $c['holidays'],
                'needs_document' => $c['type']->needsDocument($c['days'])]);
        } catch (ValidationException $e) {
            $calc = new LeaveDayCalculator($employee);
            $start = Carbon::parse($request->query('start_date'));
            $end = Carbon::parse($request->query('end_date'));

            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first(),
                'days' => $calc->businessDays($start, $end), 'return_date' => $calc->nextBusinessDay($end)->format('D, d M Y'),
                'holidays' => $calc->holidaysBetween($start, $end)->map(fn ($h) => $h->name)->values()]);
        }
    }

    public function storeLeave(Request $request, LeaveService $service)
    {
        $employee = $request->user()->employee;

        $validated = $request->validate([
            'leave_type'       => 'required|string|max:40',
            'start_date'       => 'required|date|after_or_equal:' . now()->subDays(30)->toDateString(),
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'nullable|string|max:1000',
            'supervisor_email' => 'nullable|email',
            'documents.*'      => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ], ['start_date.after_or_equal' => 'Leave more than 30 days ago has to be entered by HR.']);

        $leaveRequest = $service->apply($employee, $validated + ['company_email' => $request->user()->email],
            $this->storeDocuments($request, 'uploads/leave/documents'), $request->user());

        return redirect()->route('portal.leave')
            ->with('success', '🌴 Leave request sent! ' . $service->fmt($leaveRequest->days) . ' working day(s). You\'ll get an email once it\'s reviewed.');
    }

    public function cancelLeave(Request $request, LeaveRequest $leaveRequest, LeaveService $service)
    {
        abort_unless((int) $leaveRequest->employee_id === (int) $request->user()->employee_id, 404);
        $wasApproved = $leaveRequest->status === 'approved';
        $leave = $service->cancel($leaveRequest, $request->user(), $request->input('reason'));

        return back()->with('success', $wasApproved
            ? 'Leave cancelled. ' . ((float) $leave->deducted_days > 0 ? $service->fmt($leave->deducted_days) . ' days are back in your balance.' : '')
            : 'Leave request cancelled.');
    }

    /* ================================================================== */
    /*  OVERTIME                                                            */
    /* ================================================================== */

    public function overtime(Request $request, OvertimeService $service)
    {
        $employee = $request->user()->employee;
        $settings = $service->settings($employee->company_id);
        $daily = $service->dailyRate($employee, $settings);

        return view('portal.overtime', [
            'employee'  => $employee,
            'requests'  => OvertimeRequest::where('employee_id', $employee->id)->latest('date')->latest('id')->get(),
            'types'     => $service->types($employee->company_id),
            'settings'  => $settings,
            'hourlyRate' => round($daily / max(1, (float) $settings['hours_per_day']), 2),
            'approvedThisMonth' => (float) OvertimeRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')->whereYear('date', now()->year)->whereMonth('date', now()->month)->sum('amount'),
        ]);
    }

    public function storeOvertime(Request $request, OvertimeService $service)
    {
        $employee = $request->user()->employee;
        $validated = $request->validate([
            'date'          => 'required|date',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:720',
            'type'          => 'required|string|max:40',
            'comment'       => 'nullable|string|max:1000',
        ]);

        $ot = $service->apply($employee, $validated + ['company_email' => $request->user()->email], $request->user());

        return redirect()->route('portal.overtime')
            ->with('success', '⏱️ Overtime logged: ' . $service->fmt($ot->hours) . ' hrs (≈ K' . number_format((float) $ot->amount, 2) . ') sent for approval.');
    }

    public function cancelOvertime(Request $request, OvertimeRequest $overtimeRequest, OvertimeService $service)
    {
        abort_unless((int) $overtimeRequest->employee_id === (int) $request->user()->employee_id, 404);
        $service->cancel($overtimeRequest, $request->user(), $request->input('reason'));

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

    public function storeLoan(Request $request, LoanService $service)
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

        $service->apply($employee, $validated + ['company_email' => $request->user()->email],
            $this->storeDocuments($request, 'uploads/loans/documents'), $request->user());

        return redirect()->route('portal.loans')
            ->with('success', '💳 Loan request for K' . number_format((float) $validated['amount'], 2) . ' sent. You\'ll get an email once it\'s decided.');
    }

    public function cancelLoan(Request $request, LoanRequest $loanRequest, LoanService $service)
    {
        abort_unless((int) $loanRequest->employee_id === (int) $request->user()->employee_id, 404);
        $service->cancelRequest($loanRequest, $request->user(), $request->input('reason'));

        return back()->with('success', 'Loan request cancelled.');
    }

    /* ================================================================== */

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
