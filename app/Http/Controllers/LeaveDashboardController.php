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

        $leaveRequest->update([
            'status'      => $status,
            'hr_comment'  => $request->input('comment'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        if ($status === 'approved') {
            // Simplification: the full day-count is attributed to the month
            // the leave starts in. For leave spanning two months, HR can
            // manually split it on the master sheet afterward.
            $employee = $leaveRequest->employee;

            $record = LeaveRecord::firstOrNew([
                'employee_id' => $employee->id,
                'year'        => $leaveRequest->start_date->year,
                'month'       => $leaveRequest->start_date->month,
            ]);
            $record->days_taken = (float) ($record->days_taken ?? 0) + (float) $leaveRequest->days;
            $record->save();

            if ($employee->leave_days_balance !== null) {
                $employee->decrement('leave_days_balance', $leaveRequest->days);
            }
        }

        try {
            Mail::to($leaveRequest->employee->personal_email ?? $leaveRequest->employee->company_email)
                ->send(new LeaveDecided($leaveRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
