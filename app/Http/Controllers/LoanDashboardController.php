<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanRequest;
use App\Services\Loans\LoanService;
use App\Support\TeamScope;
use Illuminate\Http\Request;

/**
 * Loan requests and payouts. Approving creates the ledger entry ("approved — not
 * paid out yet"); recording the payout is a separate step that switches repayments on.
 */
class LoanDashboardController extends Controller
{
    public function __construct(private LoanService $loans)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'pending');
        $base = fn () => TeamScope::restrict(LoanRequest::query()->with(['employee', 'reviewedBy', 'loan']), $user);

        $requests = $base()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))))
            ->orderByDesc('created_at')->paginate(30)->withQueryString();

        $awaitingPayout = TeamScope::restrict(Loan::query()->with(['employee', 'approvedBy', 'loanRequest']), $user)
            ->where('status', 'pending_disbursement')->orderBy('approved_at')->get();

        $activeLoans = TeamScope::restrict(Loan::query(), $user)->where('status', 'active');

        return view('dashboard.loan.dashboard', [
            'requests' => $requests,
            'status' => $status,
            'counts' => [
                'pending' => $base()->where('status', 'pending')->count(),
                'approved' => $base()->where('status', 'approved')->count(),
                'rejected' => $base()->where('status', 'rejected')->count(),
                'cancelled' => $base()->where('status', 'cancelled')->count(),
            ],
            'totals' => [
                'pending_amount' => (float) $base()->where('status', 'pending')->sum('amount'),
                'awaiting_payout' => (float) $awaitingPayout->sum('principal_amount'),
                'outstanding' => (float) (clone $activeLoans)->sum('balance'),
                'active' => (clone $activeLoans)->count(),
            ],
            'awaitingPayout' => $awaitingPayout,
            'service' => $this->loans,
        ]);
    }

    public function approve(Request $request, LoanRequest $loanRequest)
    {
        $data = $request->validate([
            'comment' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:1',
            'installment_amount' => 'nullable|numeric|min:0.01',
            'first_deduction' => 'nullable|date_format:Y-m',
        ]);
        $loan = $this->loans->approve($loanRequest, $request->user(), array_filter([
            'amount' => $data['amount'] ?? null,
            'installment_amount' => $data['installment_amount'] ?? null,
            'first_deduction' => $data['first_deduction'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''), $data['comment'] ?? null);

        return back()->with('success', 'Loan of K ' . number_format((float) $loan->principal_amount, 2) . ' approved for ' . $loan->employee->full_name
            . '. Next: record the payout once the money is paid — repayments only start after that.');
    }

    public function reject(Request $request, LoanRequest $loanRequest)
    {
        $request->validate(['comment' => 'nullable|string|max:500']);
        $this->loans->reject($loanRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Loan request rejected.');
    }

    public function cancel(Request $request, LoanRequest $loanRequest)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $this->loans->cancelRequest($loanRequest, $request->user(), $request->input('reason'));

        return back()->with('success', 'Loan request cancelled.');
    }

    public function disburse(Request $request, Loan $loan)
    {
        abort_unless(TeamScope::canSee($request->user(), $loan->employee), 404);
        $data = $request->validate([
            'date' => 'required|date',
            'method' => 'required|string|max:30',
            'reference' => 'nullable|string|max:120',
            'first_deduction' => 'nullable|date_format:Y-m',
        ]);
        $loan = $this->loans->disburse($loan, $request->user(), $data);

        return back()->with('success', 'Payout recorded for ' . $loan->employee->full_name . '. Repayments start with the ' . $loan->next_deduction_date->format('F Y') . ' payroll.');
    }

    public function cancelLoan(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $this->loans->cancelLoan($loan, $request->user(), $request->input('reason'));

        return back()->with('success', 'Approved loan cancelled before payout.');
    }
}
