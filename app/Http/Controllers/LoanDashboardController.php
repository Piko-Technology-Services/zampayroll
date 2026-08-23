<?php

namespace App\Http\Controllers;

use App\Mail\LoanDecided;
use App\Models\LoanRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $requests = LoanRequest::with(['employee', 'reviewedBy', 'loan'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'pending'  => LoanRequest::where('status', 'pending')->count(),
            'approved' => LoanRequest::where('status', 'approved')->count(),
            'rejected' => LoanRequest::where('status', 'rejected')->count(),
        ];

        // A "comprehensive view" for HR: not just counts, but the money
        // involved and how many approvals are still waiting to become an
        // actual tracked loan (approved but not yet added to the ledger).
        $totals = [
            'pending_amount'  => LoanRequest::where('status', 'pending')->sum('amount'),
            'approved_amount' => LoanRequest::where('status', 'approved')->sum('amount'),
            'awaiting_ledger' => LoanRequest::where('status', 'approved')->whereDoesntHave('loan')->count(),
        ];

        return view('dashboard.loan.dashboard', compact('requests', 'status', 'counts', 'totals'));
    }

    public function approve(Request $request, LoanRequest $loanRequest)
    {
        $this->decide($request, $loanRequest, 'approved');

        return back()->with(
            'success',
            'Loan request approved. Add it to the loan ledger from the "New Loan" form when ready.'
        );
    }

    public function reject(Request $request, LoanRequest $loanRequest)
    {
        $this->decide($request, $loanRequest, 'rejected');

        return back()->with('success', 'Loan request rejected.');
    }

    /**
     * NOTE: approving here only changes the application's status and
     * notifies the employee — it deliberately does NOT create a Loan
     * ledger entry. HR adds approved loans to the ledger explicitly via
     * LoanLedgerController::create(), which offers a "prefill from an
     * approved application" picker so nothing needs retyping.
     */
    private function decide(Request $request, LoanRequest $loanRequest, string $status): void
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        $loanRequest->update([
            'status'      => $status,
            'hr_comment'  => $request->input('comment'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        try {
            Mail::to($loanRequest->employee->personal_email ?? $loanRequest->employee->company_email)
                ->send(new LoanDecided($loanRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}