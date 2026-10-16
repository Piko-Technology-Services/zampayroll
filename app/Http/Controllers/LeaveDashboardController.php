<?php

namespace App\Http\Controllers;

use App\Mail\LeaveDecided;
use App\Models\LeaveRecord;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LeaveDashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $requests = LeaveRequest::with(['employee', 'reviewedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'pending'  => LeaveRequest::where('status', 'pending')->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
        ];

        return view('dashboard.leave.dashboard', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->decide($request, $leaveRequest, 'approved');

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->decide($request, $leaveRequest, 'rejected');

        return back()->with('success', 'Leave request rejected.');
    }

    private function decide(Request $request, LeaveRequest $leaveRequest, string $status): void
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        // Shared with AI mode — see App\Services\HrActions.
        app(\App\Services\HrActions::class)->decideLeave($leaveRequest, $status, $request->input('comment'), $request->user());
    }
}
