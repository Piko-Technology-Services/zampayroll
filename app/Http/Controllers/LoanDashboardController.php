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

        return back()->with('success', 'Loan request approved.');
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

        try {
            Mail::to($loanRequest->employee->personal_email ?? $loanRequest->employee->company_email)
                ->send(new LoanDecided($loanRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
