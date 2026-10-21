<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * HR / manager leave requests: approve, reject, cancel, and record leave for
 * someone. Managers see their team; HR and administrators see everyone.
 */
class LeaveDashboardController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'open');
        $base = fn () => $this->leave->visibleQuery($user);

        $requests = $base()
            ->when($status === 'open', fn ($q) => $q->whereIn('status', LeaveRequest::OPEN))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))))
            ->when($request->query('type'), fn ($q, $t) => $q->where('leave_type', $t))
            ->orderByRaw("FIELD(status, 'manager_approved', 'pending') DESC")
            ->orderByDesc('created_at')
            ->paginate(30)->withQueryString();

        $counts = [
            'open'      => $base()->whereIn('status', LeaveRequest::OPEN)->count(),
            'approved'  => $base()->where('status', 'approved')->count(),
            'rejected'  => $base()->where('status', 'rejected')->count(),
            'cancelled' => $base()->where('status', 'cancelled')->count(),
        ];

        $types = $this->leave->types(null, false);
        $onLeaveToday = $base()->where('status', 'approved')->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->count();
        $upcoming = $base()->where('status', 'approved')->whereDate('start_date', '>', today())->whereDate('start_date', '<=', today()->addDays(14))->count();

        return view('dashboard.leave.dashboard', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
            'types' => $types,
            'typeNames' => $types->pluck('name', 'key'),
            'service' => $this->leave,
            'isHr' => $this->leave->isHr($user),
            'onLeaveToday' => $onLeaveToday,
            'upcoming' => $upcoming,
            'employees' => Employee::query()->where('employment_status', 'Active')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id', 'leave_days_balance', 'department', 'supervisor'])
                ->filter(fn ($e) => $this->leave->canSee($user, $e))->values(),
            'hrConfirm' => (bool) $user->company?->leave_hr_confirm,
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['comment' => 'nullable|string|max:500']);
        $leave = $this->leave->approve($leaveRequest, $request->user(), $request->input('comment'));

        return back()->with('success', $leave->status === 'manager_approved'
            ? 'Approved — sent to HR to confirm.'
            : 'Leave approved for ' . $leave->employee->full_name . '. Their balance, the leave sheet and attendance are updated.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['comment' => 'nullable|string|max:500']);
        $this->leave->reject($leaveRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $wasApproved = $leaveRequest->status === 'approved';
        $leave = $this->leave->cancel($leaveRequest, $request->user(), $request->input('reason'));

        return back()->with('success', 'Leave cancelled.' . ($wasApproved && (float) $leave->deducted_days > 0
            ? ' ' . $this->leave->fmt($leave->deducted_days) . ' days were given back to ' . $leave->employee->first_name . '\'s balance.' : ''));
    }

    /** HR records leave on someone's behalf (e.g. they phoned in sick). */
    public function record(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'leave_type'  => 'required|string|max:40',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date',
            'reason'      => 'nullable|string|max:1000',
            'approve_now' => 'nullable|boolean',
            'documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        abort_unless($this->leave->canSee($request->user(), $employee), 404);

        $docs = $request->hasFile('documents') ? collect($request->file('documents'))->map(fn ($f) => [
            'name' => $f->getClientOriginalName(), 'path' => $f->store('uploads/leave/documents', 'public'), 'uploaded_at' => now()->toDateTimeString(),
        ])->all() : null;

        $leave = $this->leave->apply($employee, $data, $docs, $request->user(), notify: empty($data['approve_now']));
        if (! empty($data['approve_now'])) {
            try {
                $leave = $this->leave->approve($leave, $request->user(), 'Recorded by ' . $request->user()->name);
            } catch (ValidationException $e) {
                return back()->with('success', 'Leave recorded for ' . $employee->full_name . ', but it still needs approval: ' . collect($e->errors())->flatten()->first());
            }
        }

        return back()->with('success', 'Leave recorded for ' . $employee->full_name . ' — ' . $this->leave->fmt($leave->days) . ' working days'
            . ($leave->status === 'approved' ? ', approved.' : ', waiting for approval.'));
    }

    /** Live working-day count for the "record leave" form. */
    public function check(Request $request)
    {
        $request->validate(['employee_id' => 'required|integer', 'leave_type' => 'required|string', 'start_date' => 'required|date', 'end_date' => 'required|date']);
        $employee = Employee::query()->findOrFail($request->query('employee_id'));
        abort_unless($this->leave->canSee($request->user(), $employee), 404);

        try {
            $c = $this->leave->check($employee, $request->query('leave_type'), $request->query('start_date'), $request->query('end_date'), 1, null, true);

            return response()->json(['ok' => true, 'days' => $c['days'], 'return_date' => $c['return_date']->format('D, j M Y'), 'holidays' => $c['holidays'],
                'balance' => $this->leave->balance($employee)]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first(), 'balance' => $this->leave->balance($employee)]);
        }
    }
}
