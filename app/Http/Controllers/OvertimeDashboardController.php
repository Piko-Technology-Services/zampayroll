<?php

namespace App\Http\Controllers;

use App\Mail\OvertimeDecided;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OvertimeDashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $requests = OvertimeRequest::with(['employee', 'reviewedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'pending'  => OvertimeRequest::where('status', 'pending')->count(),
            'approved' => OvertimeRequest::where('status', 'approved')->count(),
            'rejected' => OvertimeRequest::where('status', 'rejected')->count(),
        ];

        return view('dashboard.overtime.dashboard', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->decide($request, $overtimeRequest, 'approved');

        return back()->with('success', 'Overtime request approved.');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->decide($request, $overtimeRequest, 'rejected');

        return back()->with('success', 'Overtime request rejected.');
    }

    private function decide(Request $request, OvertimeRequest $overtimeRequest, string $status): void
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        $overtimeRequest->update([
            'status'      => $status,
            'hr_comment'  => $request->input('comment'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        try {
            Mail::to($overtimeRequest->employee->personal_email ?? $overtimeRequest->employee->company_email)
                ->send(new OvertimeDecided($overtimeRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
