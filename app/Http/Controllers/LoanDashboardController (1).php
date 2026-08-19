<?php

namespace App\Http\Controllers;

use App\Mail\LoanDecided;
use App\Models\Loan;
use App\Models\LoanRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $requests = LoanRequest::with(['employee', 'reviewedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'pending'  => LoanRequest::where('status', 'pending')->count(),
            'approved' => LoanRequest::where('status', 'approved')->count(),
            'rejected' => LoanRequest::where('status', 'rejected')->count(),
        ];

        return view('dashboard.loan.dashboard', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, LoanRequest $loanRequest)
    {
        $this->decide($request, $loanRequest, 'approved');

        return back()->with('success', 'Loan request approved and added to the loan ledger.');
    }

    public function reject(Request $request, LoanRequest $loanRequest)
    {
        $this->decide($request, $loanRequest, 'rejected');

        return back()->with('success', 'Loan request rejected.');
    }

    private function decide(Request $request, LoanRequest $loanRequest, string $status): void
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        $loanRequest->update([
            'status'      => $status,
            'hr_comment'  => $request->input('comment'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        if ($status === 'approved') {
            $this->createLedgerEntry($request, $loanRequest);
        }

        try {
            Mail::to($loanRequest->employee->personal_email ?? $loanRequest->employee->company_email)
                ->send(new LoanDecided($loanRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Turn an approved application into an actual ledger entry with a
     * running balance. NOTE: the application only captures a payment_plan
     * label + optional free-text note (e.g. "K500 every 3 months") — it
     * doesn't capture a structured installment amount. For monthly/
     * bi_monthly/other plans, HR needs to open the new Loan in the ledger
     * and set the Installment Amount before it will start deducting —
     * the ledger view flags any loan missing one. once_off plans need no
     * installment amount; they deduct the full balance in the next run.
     */
    private function createLedgerEntry(Request $request, LoanRequest $loanRequest): void
    {
        $loan = Loan::create([
            'employee_id'         => $loanRequest->employee_id,
            'loan_request_id'     => $loanRequest->id,
            'principal_amount'    => $loanRequest->amount,
            'balance'             => $loanRequest->amount,
            'payment_plan'        => $loanRequest->payment_plan,
            'payment_plan_note'   => $loanRequest->payment_plan_note,
            'installment_amount'  => null,
            'start_date'          => now()->toDateString(),
            'next_deduction_date' => now()->toDateString(),
            'status'              => 'active',
            'created_by'          => $request->user()?->id,
        ]);

        Loan::syncEmployeeCache($loan->employee_id);
    }
}
