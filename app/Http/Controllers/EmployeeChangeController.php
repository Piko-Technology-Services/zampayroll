<?php

namespace App\Http\Controllers;

use App\Mail\ChangeRequestMail;
use App\Models\EmployeeChangeRequest;
use App\Support\Audit;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * HR checks bank-detail changes employees asked for in the portal before they are applied.
 */
class EmployeeChangeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('dashboard.employees.changes', [
            'status' => $status,
            'changes' => EmployeeChangeRequest::query()->with(['employee', 'reviewedBy'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'counts' => EmployeeChangeRequest::query()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all(),
        ]);
    }

    public function approve(Request $request, EmployeeChangeRequest $change)
    {
        $request->validate(['comment' => 'nullable|string|max:500', 'confirmed' => 'accepted'], ['confirmed.accepted' => 'Tick the box to confirm you checked this with the employee.']);

        DB::transaction(function () use (&$change, $request) {
            $change = EmployeeChangeRequest::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($change->status === 'pending', 422, 'This change was already ' . $change->status . '.');
            abort_if((int) $request->user()->employee_id === (int) $change->employee_id, 403, 'You can\'t approve a change to your own bank details.');

            $employee = $change->employee()->lockForUpdate()->firstOrFail();
            $allowed = array_keys(EmployeeChangeRequest::FIELDS[$change->type] ?? []);
            $apply = array_intersect_key($change->changes ?? [], array_flip($allowed));
            $before = $employee->only(array_keys($apply));
            $employee->forceFill($apply)->save();

            $change->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_comment' => $request->input('comment')]);
            Audit::record('employee.bank_changed', $employee, $employee->full_name . ': bank details changed (request approved by ' . $request->user()->name . ')',
                collect($apply)->map(fn ($v, $k) => ['from' => $before[$k] ?? null, 'to' => $v])->all());
        });

        $this->tell($change);

        return back()->with('success', 'Bank details updated for ' . $change->employee->full_name . '. Payroll and bank files use the new account from now on.');
    }

    public function reject(Request $request, EmployeeChangeRequest $change)
    {
        $request->validate(['comment' => 'nullable|string|max:500']);
        abort_unless($change->status === 'pending', 422);
        $change->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_comment' => $request->input('comment')]);
        Audit::record('employee.change_rejected', $change, $change->employee->full_name . ': bank details change rejected' . ($request->input('comment') ? ' — ' . $request->input('comment') : ''));
        $this->tell($change);

        return back()->with('success', 'Change rejected. The employee has been told.');
    }

    public function document(Request $request, EmployeeChangeRequest $change)
    {
        $doc = ($change->documents ?? [])[0] ?? null;
        abort_unless($doc && Storage::disk('local')->exists($doc['path']), 404);

        return Storage::disk('local')->download($doc['path'], $doc['name'] ?? 'document');
    }

    private function tell(EmployeeChangeRequest $change): void
    {
        $email = $change->employee?->user?->email ?: $change->employee?->company_email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new ChangeRequestMail($change->fresh(), 'decided'));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
