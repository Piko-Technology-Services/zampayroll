<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Support\Tenancy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Employee self-service: dashboard, payslips, profile and password.
 */
class PortalController extends Controller
{
    /** Payroll statuses an employee is allowed to see (finalised runs only). */
    private const VISIBLE_STATUSES = ['Approved', 'Paid', 'Finalized', 'Finalised'];

    public function dashboard(Request $request)
    {
        $employee = $request->user()->employee;

        $latestPayslip = $this->payslipQuery($employee->id)->first();

        $pending = [
            'leave'    => LeaveRequest::where('employee_id', $employee->id)->whereIn('status', LeaveRequest::OPEN)->count(),
            'overtime' => OvertimeRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            'loans'    => LoanRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
        ];

        $activeLoans = Loan::where('employee_id', $employee->id)->where('status', 'active')->get();

        $recent = collect()
            ->merge(LeaveRequest::where('employee_id', $employee->id)->latest()->take(5)->get()->map(fn ($r) => [
                'type' => 'Leave', 'icon' => 'bi-calendar2-heart', 'title' => LeaveRequest::TYPES[$r->leave_type] ?? 'Leave',
                'detail' => $r->start_date->format('d M') . ' – ' . $r->end_date->format('d M Y') . ' · ' . rtrim(rtrim((string) $r->days, '0'), '.') . ' day(s)',
                'status' => $r->status, 'at' => $r->created_at, 'url' => route('portal.leave'),
            ]))
            ->merge(OvertimeRequest::where('employee_id', $employee->id)->latest()->take(5)->get()->map(fn ($r) => [
                'type' => 'Overtime', 'icon' => 'bi-alarm', 'title' => $r->typeLabel(),
                'detail' => $r->date->format('d M Y') . ' · ' . rtrim(rtrim((string) $r->hours, '0'), '.') . ' hrs · K' . number_format((float) $r->amount, 2),
                'status' => $r->status, 'at' => $r->created_at, 'url' => route('portal.overtime'),
            ]))
            ->merge(LoanRequest::where('employee_id', $employee->id)->latest()->take(5)->get()->map(fn ($r) => [
                'type' => 'Loan', 'icon' => 'bi-cash-coin', 'title' => 'Loan request',
                'detail' => 'K' . number_format((float) $r->amount, 2) . ' · ' . (LoanRequest::PAYMENT_PLANS[$r->payment_plan] ?? $r->payment_plan),
                'status' => $r->status, 'at' => $r->created_at, 'url' => route('portal.loans'),
            ]))
            ->sortByDesc('at')->take(6)->values();

        return view('portal.dashboard', [
            'employee'      => $employee,
            'company'       => Tenancy::company(),
            'latestPayslip' => $latestPayslip,
            'payslipCount'  => $this->payslipQuery($employee->id)->count(),
            'pending'       => $pending,
            'activeLoans'   => $activeLoans,
            'recent'        => $recent,
            'greeting'      => $this->greeting(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Payslips                                                            */
    /* ------------------------------------------------------------------ */

    public function payslips(Request $request)
    {
        $employee = $request->user()->employee;

        $payslips = $this->payslipQuery($employee->id)->get();

        $byYear = $payslips->groupBy(fn (Payroll $p) => optional($p->run?->period_date)->year ?? $p->created_at->year);

        return view('portal.payslips', [
            'employee' => $employee,
            'byYear'   => $byYear,
            'ytd'      => [
                'gross' => $payslips->filter(fn ($p) => (optional($p->run?->period_date)->year ?? $p->created_at->year) == now()->year)->sum('total_income'),
                'net'   => $payslips->filter(fn ($p) => (optional($p->run?->period_date)->year ?? $p->created_at->year) == now()->year)->sum('net_pay'),
            ],
        ]);
    }

    public function showPayslip(Request $request, Payroll $payroll)
    {
        $this->authorizePayslip($request, $payroll);

        $payroll->load(['employee', 'items']);
        $run = $payroll->run;
        $run->setRelation('payrolls', collect([$payroll]));

        return view('dashboard.payroll.runs.payslip_single', [
            'run'         => $run,
            'payroll'     => $payroll,
            'companyName' => Tenancy::company()?->name ?? 'ZamPayroll',
            'companyLogo' => 'http://misc.zampayroll.com/logo.png',
            'portalBackUrl' => route('portal.payslips'),
        ]);
    }

    public function downloadPayslip(Request $request, Payroll $payroll)
    {
        $this->authorizePayslip($request, $payroll);

        $payroll->load(['employee', 'items']);
        $run = $payroll->run;
        $run->setRelation('payrolls', collect([$payroll]));

        $pdf = Pdf::loadView('dashboard.payroll.runs.payslip_pdf', [
            'run'         => $run,
            'payroll'     => $payroll,
            'companyName' => Tenancy::company()?->name ?? 'ZamPayroll',
            'companyLogo' => 'http://misc.zampayroll.com/logo.png',
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('payslip-%s-%s.pdf',
            $payroll->employee->employee_id,
            str_replace(' ', '-', strtolower($payroll->pay_period ?: $run->period))
        );

        return $pdf->download($filename);
    }

    /* ------------------------------------------------------------------ */
    /*  Profile                                                             */
    /* ------------------------------------------------------------------ */

    public function profile(Request $request)
    {
        $employee = $request->user()->employee;

        return view('portal.profile', [
            'employee' => $employee,
            'user'     => $request->user(),
            'changes'  => \App\Models\EmployeeChangeRequest::query()->where('employee_id', $employee->id)->latest()->take(5)->get(),
            'banks'    => \App\Models\Bank::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
        ]);
    }

    /**
     * Employees may only update their own contact & emergency details —
     * job, salary and bank details stay under HR's control.
     */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'personal_email'         => 'nullable|email|max:255',
            'primary_phone'          => 'required|string|max:50',
            'secondary_phone'        => 'nullable|string|max:50',
            'emergency_name'         => 'nullable|string|max:255',
            'emergency_relationship' => 'nullable|string|max:100',
            'emergency_phone'        => 'nullable|string|max:50',
            'next_of_kin_name'       => 'nullable|string|max:255',
            'next_of_kin_phone'      => 'nullable|string|max:50',
            'next_of_kin_address'    => 'nullable|string|max:1000',
        ]);

        $employee = $request->user()->employee;
        $before = $employee->only(array_keys($data));
        $employee->update($data);
        $request->user()->forceFill(['phone' => $data['primary_phone']])->save();

        $changes = collect($data)->filter(fn ($v, $k) => (string) ($before[$k] ?? '') !== (string) ($v ?? ''))
            ->map(fn ($v, $k) => ['from' => $before[$k] ?? null, 'to' => $v])->all();
        if ($changes) {
            \App\Support\Audit::record('employee.self_updated', $employee, $employee->full_name . ' updated their contact details: ' . implode(', ', array_keys($changes)), $changes);
        }

        return back()->with('success', 'Your details have been updated. ✅');
    }

    /** My attendance for a month. */
    public function attendance(Request $request)
    {
        $employee = $request->user()->employee;
        $month = $request->query('month') && preg_match('/^\d{4}-\d{2}$/', $request->query('month'))
            ? \Carbon\Carbon::parse($request->query('month') . '-01') : now()->startOfMonth();
        $records = \App\Models\Attendance::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()->keyBy(fn ($a) => $a->date->toDateString());
        $calc = new \App\Services\LeaveDayCalculator($employee);

        $days = [];
        for ($d = $month->copy()->startOfMonth(); $d->lte($month->copy()->endOfMonth()); $d->addDay()) {
            $rec = $records->get($d->toDateString());
            $days[] = ['date' => $d->copy(), 'record' => $rec, 'working' => $calc->isWorkingDay($d), 'future' => $d->isAfter(today())];
        }

        return view('portal.attendance', [
            'employee' => $employee,
            'month' => $month,
            'days' => array_reverse($days), // newest first
            'counts' => $records->countBy('status')->all(),
            'hours' => (float) $records->sum('hours_worked'),
        ]);
    }

    /** Ask HR to change the bank account salary is paid into. HR approves before anything changes. */
    public function requestBankChange(Request $request)
    {
        $employee = $request->user()->employee;
        $data = $request->validate([
            'bank_name' => 'required|string|max:120',
            'bank_branch' => 'nullable|string|max:120',
            'bank_account_no' => ['required', 'string', 'max:40', 'regex:/^[0-9 \-]{6,40}$/'],
            'bank_account_no_confirmation' => 'required|same:bank_account_no',
            'note' => 'nullable|string|max:500',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'bank_account_no.regex' => 'Use numbers only for the account number.',
            'bank_account_no_confirmation.same' => 'The two account numbers don\'t match.',
        ]);

        if (\App\Models\EmployeeChangeRequest::query()->where('employee_id', $employee->id)->where('status', 'pending')->exists()) {
            return back()->withErrors(['bank_name' => 'You already have a change waiting for HR. Cancel it first to send a new one.']);
        }

        $changes = ['bank_name' => trim($data['bank_name']), 'bank_branch' => $data['bank_branch'] ?? null, 'bank_account_no' => preg_replace('/\D/', '', $data['bank_account_no'])];
        $change = \App\Models\EmployeeChangeRequest::create([
            'employee_id' => $employee->id, 'type' => 'bank', 'changes' => $changes,
            'previous' => $employee->only(array_keys($changes)), 'note' => $data['note'] ?? null,
            'documents' => $request->hasFile('document') ? [['name' => $request->file('document')->getClientOriginalName(), 'path' => $request->file('document')->store('employee-changes/' . $employee->company_id, 'local')]] : null,
            'status' => 'pending', 'requested_by' => $request->user()->id,
        ]);
        \App\Support\Audit::record('employee.bank_change_requested', $change, $employee->full_name . ' asked to change their bank account (to ' . $changes['bank_name'] . ' ••••' . substr($changes['bank_account_no'], -4) . ')');

        $this->mailQuietly([$request->user()->email], new \App\Mail\ChangeRequestMail($change, 'requested'));
        $this->mailQuietly(\App\Support\TeamScope::approverEmails($employee, 'employees.manage', true), new \App\Mail\ChangeRequestMail($change, 'hr'));

        return back()->with('success', 'Sent to HR. Your salary keeps going to your current account until they approve the change.');
    }

    public function cancelChange(Request $request, \App\Models\EmployeeChangeRequest $change)
    {
        abort_unless((int) $change->employee_id === (int) $request->user()->employee_id, 404);
        abort_unless($change->status === 'pending', 422, 'Only waiting requests can be cancelled.');
        $change->update(['status' => 'cancelled']);
        \App\Support\Audit::record('employee.change_cancelled', $change, $change->employee->full_name . ' cancelled their ' . strtolower($change->typeLabel()) . ' change');

        return back()->with('success', 'Change request cancelled.');
    }

    private function mailQuietly(array $to, $mailable): void
    {
        $to = array_values(array_filter($to));
        if (! $to) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function editPassword(Request $request)
    {
        return view('portal.password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => $user->must_change_password
                ? 'That isn\'t the temporary password from your welcome email.'
                : 'Your current password is incorrect.',
            'password.different' => 'Choose a password that is different from the temporary one.',
        ]);

        $wasForced = $user->must_change_password;

        $user->forceFill([
            'password'             => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route($wasForced ? 'portal.dashboard' : 'portal.profile')
            ->with('success', $wasForced
                ? '🎉 You\'re all set! Welcome to your self-service portal.'
                : 'Password changed successfully.')
            ->with('celebrate', $wasForced);
    }

    /* ------------------------------------------------------------------ */

    private function payslipQuery(int $employeeId)
    {
        return Payroll::with('run')
            ->where('employee_id', $employeeId)
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->whereHas('run', fn ($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('payroll_run_id')
            ->orderByDesc('id');
    }

    private function authorizePayslip(Request $request, Payroll $payroll): void
    {
        abort_unless(
            (int) $payroll->employee_id === (int) $request->user()->employee_id
            && in_array($payroll->status, self::VISIBLE_STATUSES, true)
            && $payroll->run !== null,
            404
        );
    }

    private function greeting(): string
    {
        $hour = (int) now('Africa/Lusaka')->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
    }
}
