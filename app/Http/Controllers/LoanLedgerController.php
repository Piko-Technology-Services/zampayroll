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

        return view('dashboard.loan.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'principal_amount'    => 'required|numeric|min:0.01',
            'balance'             => 'nullable|numeric|min:0',
            'payment_plan'        => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'   => 'nullable|string|max:255',
            'installment_amount'  => 'nullable|numeric|min:0',
            'start_date'          => 'required|date',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $loan = Loan::create([
            'employee_id'         => $validated['employee_id'],
            'principal_amount'    => $validated['principal_amount'],
            'balance'             => $validated['balance'] ?? $validated['principal_amount'],
            'payment_plan'        => $validated['payment_plan'],
            'payment_plan_note'   => $validated['payment_plan_note'] ?? null,
            'installment_amount'  => $validated['installment_amount'] ?? null,
            'start_date'          => $validated['start_date'],
            'next_deduction_date' => $validated['start_date'],
            'status'              => 'active',
            'notes'               => $validated['notes'] ?? null,
            'created_by'          => $request->user()?->id,
        ]);

        Loan::syncEmployeeCache($loan->employee_id);

        return redirect()->route('loan.ledger.show', $loan)->with('success', 'Loan added to the ledger.');
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

        $isWriteOff = $validated['type'] === 'write_off';
        $amount     = $isWriteOff ? (float) $loan->balance : (float) $validated['amount'];
        $newBalance = $isWriteOff ? 0 : round(max(0, (float) $loan->balance - $amount), 2);

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

        Loan::syncEmployeeCache($loan->employee_id);

        return back()->with('success', $isWriteOff ? 'Loan written off.' : 'Payment recorded.');
    }

    public function updatePlan(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'payment_plan'         => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'    => 'nullable|string|max:255',
            'installment_amount'   => 'nullable|numeric|min:0',
            'next_deduction_date'  => 'nullable|date',
            'status'               => 'required|in:' . implode(',', array_keys(Loan::STATUSES)),
        ]);

        $loan->update($validated);

        Loan::syncEmployeeCache($loan->employee_id);

        return back()->with('success', 'Loan updated.');
    }
}
