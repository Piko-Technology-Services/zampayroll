<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRequest;
use Illuminate\Http\Request;

class LoanLedgerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');

        $loans = Loan::with('employee')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->where('department', $d)
            ))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'active'      => Loan::where('status', 'active')->count(),
            'completed'   => Loan::where('status', 'completed')->count(),
            'paused'      => Loan::where('status', 'paused')->count(),
            'written_off' => Loan::where('status', 'written_off')->count(),
        ];

        $totals = [
            'outstanding' => Loan::where('status', 'active')->sum('balance'),
            'disbursed'   => Loan::sum('principal_amount'),
        ];

        $departments = Employee::where('employment_status', 'Active')
            ->pluck('department')->filter()->unique()->sort()->values();

        return view('dashboard.loan.ledger', compact('loans', 'status', 'counts', 'totals', 'departments'));
    }

    public function create()
    {
        $employees = Employee::where('employment_status', 'Active')
            ->orderBy('first_name')
            ->get();

        $employeeIdsWithActiveLoans = Loan::where('status', 'active')->pluck('employee_id');

        $approvedApplications = LoanRequest::with('employee')
            ->where('status', 'approved')
            ->whereDoesntHave('loan')
            ->whereNotIn('employee_id', $employeeIdsWithActiveLoans)
            ->orderByDesc('reviewed_at')
            ->get();

        return view('dashboard.loan.create', compact('employees', 'approvedApplications'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'loan_request_id'     => 'nullable|exists:loan_requests,id,company_id,' . (int) auth()->user()->company_id,
            'employee_id'         => 'required|exists:employees,id,company_id,' . (int) auth()->user()->company_id,
            'principal_amount'    => 'required|numeric|min:0.01',
            'balance'             => 'nullable|numeric|min:0',
            'payment_plan'        => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'   => 'nullable|string|max:255',
            'installment_amount'  => 'nullable|numeric|min:0',
            'start_date'          => 'required|date',
            'notes'               => 'nullable|string|max:1000',
        ]);

        // Entered straight onto the ledger: it is approved by whoever enters it. Repayments only
        // start once it is recorded as paid out (now, if it already was, or later from the loan page).
        $paidOut = $request->boolean('paid_out');
        if ($paidOut) {
            abort_unless($request->user()->hasPermission('loans.disburse'), 403, 'You don\'t have permission to record loan payouts.');
            $request->validate([
                'disbursed_at' => 'required|date|before_or_equal:today',
                'disbursement_method' => 'required|in:' . implode(',', array_keys(Loan::DISBURSEMENT_METHODS)),
                'disbursement_reference' => 'nullable|string|max:120',
            ]);
        }

        $loan = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request, $paidOut) {
            if (! empty($validated['loan_request_id']) && Loan::where('loan_request_id', $validated['loan_request_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['loan_request_id' => 'That application is already on the ledger.']);
            }

            $loan = Loan::create([
                'employee_id'         => $validated['employee_id'],
                'loan_request_id'     => $validated['loan_request_id'] ?? null,
                'principal_amount'    => $validated['principal_amount'],
                'balance'             => $validated['balance'] ?? $validated['principal_amount'],
                'payment_plan'        => $validated['payment_plan'],
                'payment_plan_note'   => $validated['payment_plan_note'] ?? null,
                'installment_amount'  => $validated['installment_amount'] ?? null,
                'start_date'          => $validated['start_date'],
                'next_deduction_date' => $validated['start_date'],
                'status'              => $paidOut ? 'active' : 'pending_disbursement',
                'notes'               => $validated['notes'] ?? null,
                'created_by'          => $request->user()?->id,
                'approved_by'         => $request->user()?->id,
                'approved_at'         => now(),
                'disbursed_at'        => $paidOut ? $request->input('disbursed_at') : null,
                'disbursed_by'        => $paidOut ? $request->user()->id : null,
                'disbursement_method' => $paidOut ? $request->input('disbursement_method') : null,
                'disbursement_reference' => $paidOut ? $request->input('disbursement_reference') : null,
            ]);

            \App\Support\Audit::record('loan.created', $loan, $loan->employee->full_name . ': loan of K ' . number_format((float) $loan->principal_amount, 2) . ' entered on the ledger'
                . ($paidOut ? ', already paid out' : ', waiting to be paid out'));

            return $loan;
        });

        Loan::syncEmployeeCache($loan->employee_id);

        return redirect()->route('loan.ledger.show', $loan)->with('success', $loan->status === 'active'
            ? 'Loan added to the ledger. Repayments come out of pay from ' . $loan->next_deduction_date->format('F Y') . '.'
            : 'Loan added. Record the payout once the money is paid — repayments start after that.');
    }

    public function show(Loan $loan)
    {
        $loan->load([
            'employee',
            'payments' => fn ($q) => $q->orderByDesc('created_at'),
            'payments.recordedBy',
            'payments.payroll.payrollRun',
        ]);

        return view('dashboard.loan.show', compact('loan'));
    }

    public function recordPayment(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type'   => 'required|in:manual_payment,adjustment,write_off',
            'note'   => 'nullable|string|max:255',
        ]);

        if (! $loan->isDisbursed() || in_array($loan->status, ['completed', 'written_off', 'cancelled'], true)) {
            return back()->withErrors(['amount' => $loan->isDisbursed() ? 'This loan is already closed.' : 'This loan hasn\'t been paid out yet, so there is nothing to repay.']);
        }
        if ($validated['type'] !== 'write_off' && (float) $validated['amount'] > (float) $loan->balance + 0.001) {
            return back()->withErrors(['amount' => 'That is more than the K ' . number_format((float) $loan->balance, 2) . ' still owed.']);
        }

        $isWriteOff = $validated['type'] === 'write_off';
        $amount     = $isWriteOff ? (float) $loan->balance : (float) $validated['amount'];
        $newBalance = $isWriteOff ? 0 : round(max(0, (float) $loan->balance - $amount), 2);

        \Illuminate\Support\Facades\DB::transaction(function () use ($loan, $amount, $newBalance, $validated, $request, $isWriteOff) {
        LoanPayment::create([
            'loan_id'       => $loan->id,
            'payroll_id'    => null,
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'type'          => $validated['type'],
            'note'          => $validated['note'] ?? null,
            'recorded_by'   => $request->user()?->id,
        ]);

        $loan->update([
            'balance' => $newBalance,
            'status'  => $newBalance <= 0
                ? ($isWriteOff ? 'written_off' : 'completed')
                : $loan->status,
        ]);
        \App\Support\Audit::record($isWriteOff ? 'loan.written_off' : 'loan.payment_recorded', $loan, $loan->employee->full_name . ': '
            . ($isWriteOff ? 'loan written off (K ' . number_format($amount, 2) . ')' : str_replace('_', ' ', $validated['type']) . ' of K ' . number_format($amount, 2) . ', K ' . number_format($newBalance, 2) . ' left'));
        });

        Loan::syncEmployeeCache($loan->employee_id);

        return back()->with('success', $isWriteOff ? 'Loan written off.' : 'Payment recorded.');
    }

    public function updatePlan(Request $request, Loan $loan)
    {
        // Status can only move between running and paused here; payouts, write-offs and
        // cancellations have their own recorded steps.
        $allowed = $loan->isDisbursed() ? ['active', 'paused'] : [$loan->status];
        if (in_array($loan->status, ['completed', 'written_off', 'cancelled'], true)) {
            $allowed = [$loan->status];
        }
        $validated = $request->validate([
            'payment_plan'         => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'    => 'nullable|string|max:255',
            'installment_amount'   => 'nullable|numeric|min:0',
            'next_deduction_date'  => 'nullable|date',
            'status'               => 'required|in:' . implode(',', $allowed),
        ], ['status.in' => $loan->isDisbursed() ? 'Use the payment form to write off or settle a loan.' : 'Record the payout first — that starts repayments.']);

        $before = $loan->only(array_keys($validated));
        $loan->update($validated);
        $changes = collect($validated)->filter(fn ($v, $k) => (string) ($before[$k] instanceof \DateTimeInterface ? $before[$k]->format('Y-m-d') : $before[$k]) !== (string) $v)
            ->map(fn ($v, $k) => ['from' => $before[$k] instanceof \DateTimeInterface ? $before[$k]->format('Y-m-d') : $before[$k], 'to' => $v])->all();
        if ($changes) {
            \App\Support\Audit::record('loan.terms_changed', $loan, $loan->employee->full_name . ': loan terms changed', $changes);
        }

        Loan::syncEmployeeCache($loan->employee_id);

        return back()->with('success', 'Loan updated.');
    }
}
