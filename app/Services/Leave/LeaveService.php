<?php

namespace App\Services\Leave;

use App\Mail\LeaveApplied;
use App\Mail\LeaveDecided;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveDayCalculator;
use App\Support\Audit;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Everything that changes leave goes through here, so the portal, the public
 * form, HR screens and AI mode all follow the same rules:
 *
 *   apply   → checks dates, overlaps, balance, limits, documents
 *   approve → manager (and HR, if the company wants a second check)
 *             → takes days off the balance, fills the leave sheet, marks attendance
 *   reject
 *   cancel  → kept as a record; if it was approved, everything above is given back
 *
 * Each step runs in a database transaction with the request row locked, and is audited.
 */
class LeaveService
{
    /* ================================================================== */
    /*  Types                                                               */
    /* ================================================================== */

    /** The company's leave types (created from the defaults the first time). */
    public function types(?int $companyId = null, bool $activeOnly = true): Collection
    {
        $companyId ??= \App\Support\Tenancy::companyId();
        $this->ensureDefaults($companyId);

        return LeaveType::query()
            ->when($companyId, fn ($q) => $q->forCompany($companyId))
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort')->orderBy('id')->get();
    }

    public function type(string $key, ?int $companyId = null): ?LeaveType
    {
        return $this->types($companyId, false)->firstWhere('key', $key);
    }

    public function ensureDefaults(?int $companyId): void
    {
        if (! $companyId || LeaveType::query()->forCompany($companyId)->exists()) {
            return;
        }
        $sort = 0;
        foreach (LeaveType::DEFAULTS as $key => [$name, $paid, $deducts, $doc, $desc]) {
            LeaveType::query()->forCompany($companyId)->firstOrCreate(['company_id' => $companyId, 'key' => $key], [
                'name' => $name, 'is_paid' => $paid, 'deducts_balance' => $deducts, 'requires_document' => $doc,
                'description' => $desc, 'sort' => $sort += 10,
            ]);
        }
    }

    /* ================================================================== */
    /*  Balances                                                            */
    /* ================================================================== */

    /**
     * @return array{entitled: float, balance: ?float, pending: float, available: ?float, taken_this_year: float}
     */
    public function balance(Employee $employee): array
    {
        $deducting = $this->types($employee->company_id, false)->where('deducts_balance', true)->pluck('key')->all();
        $pending = (float) LeaveRequest::query()->where('employee_id', $employee->id)
            ->whereIn('status', LeaveRequest::OPEN)->whereIn('leave_type', $deducting)->sum('days');
        $taken = (float) LeaveRequest::query()->where('employee_id', $employee->id)
            ->where('status', 'approved')->whereIn('leave_type', $deducting)->whereYear('start_date', now()->year)->sum('days');
        $balance = $employee->leave_days_balance !== null ? (float) $employee->leave_days_balance : null;

        return [
            'entitled' => (float) ($employee->leave_days_entitled ?? 0),
            'balance' => $balance,
            'pending' => $pending,
            'available' => $balance !== null ? round($balance - $pending, 2) : null,
            'taken_this_year' => $taken,
        ];
    }

    /** Days of a type already used or asked for this year (for types with a yearly limit). */
    public function usedOfType(Employee $employee, string $key, int $year, ?int $ignoreId = null): float
    {
        return (float) LeaveRequest::query()->where('employee_id', $employee->id)->where('leave_type', $key)
            ->whereIn('status', array_merge(LeaveRequest::OPEN, ['approved']))->whereYear('start_date', $year)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->sum('days');
    }

    /* ================================================================== */
    /*  Apply                                                               */
    /* ================================================================== */

    /**
     * Work out the days and check every rule. Throws ValidationException with
     * friendly messages keyed by form field.
     *
     * @return array{type: LeaveType, days: float, return_date: Carbon, start: Carbon, end: Carbon, holidays: array}
     */
    public function check(Employee $employee, string $typeKey, $start, $end, int $documents = 0, ?int $ignoreId = null, bool $byHr = false): array
    {
        $type = $this->type($typeKey, $employee->company_id);
        if (! $type || ! $type->is_active || (! $byHr && ! $type->employees_can_apply)) {
            throw ValidationException::withMessages(['leave_type' => 'Please choose one of the leave types on the list.']);
        }

        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'The last day can\'t be before the first day.']);
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Leave can\'t be longer than a year in one request.']);
        }

        $calc = new LeaveDayCalculator($employee);
        $days = (float) $calc->businessDays($start, $end);
        if ($days <= 0) {
            throw ValidationException::withMessages(['end_date' => 'Those dates fall entirely on non-working days or public holidays.']);
        }

        $overlap = LeaveRequest::query()->where('employee_id', $employee->id)
            ->whereIn('status', array_merge(LeaveRequest::OPEN, ['approved']))
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->first();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'There is already a leave request for ' . $overlap->start_date->format('j M') . ' – ' . $overlap->end_date->format('j M Y') . ' that overlaps these dates.']);
        }

        if ($type->max_days_per_request !== null && $days > (float) $type->max_days_per_request) {
            throw ValidationException::withMessages(['end_date' => "{$type->name} can be at most " . $this->fmt($type->max_days_per_request) . ' working days per request.']);
        }

        if ($type->deducts_balance && ! $this->allowNegative($employee)) {
            $bal = $this->balance($employee);
            if ($bal['available'] !== null) {
                $available = $bal['available'];
                if ($ignoreId && ($own = LeaveRequest::find($ignoreId)) && $own->isOpen()) {
                    $available += (float) $own->days;
                }
                if ($days > $available) {
                    throw ValidationException::withMessages(['end_date' => 'That is ' . $this->fmt($days) . ' working days, but only ' . $this->fmt(max(0, $available)) . ' days are left'
                        . ($bal['pending'] > 0 ? ' (after ' . $this->fmt($bal['pending']) . ' days already waiting for approval)' : '') . '.']);
                }
            }
        }

        if ($type->days_per_year !== null) {
            $used = $this->usedOfType($employee, $type->key, $start->year, $ignoreId);
            if ($used + $days > (float) $type->days_per_year) {
                throw ValidationException::withMessages(['end_date' => "{$type->name} is limited to " . $this->fmt($type->days_per_year) . " days a year — {$this->fmt($used)} already used or requested."]);
            }
        }

        if ($type->needsDocument($days) && $documents < 1) {
            throw ValidationException::withMessages(['documents' => "Please attach a supporting document for {$type->name}" . ($type->document_after_days ? ' longer than ' . $type->document_after_days . ' day' . ($type->document_after_days === 1 ? '' : 's') : '') . '.']);
        }

        return [
            'type' => $type, 'days' => $days, 'start' => $start, 'end' => $end,
            'return_date' => $calc->nextBusinessDay($end),
            'holidays' => $calc->holidaysBetween($start, $end)->pluck('name')->values()->all(),
        ];
    }

    /**
     * Create a request. $by is set when HR records it for the employee.
     *
     * @param  array  $data  leave_type, start_date, end_date, reason?, supervisor_email?, company_email?
     */
    public function apply(Employee $employee, array $data, ?array $documents = null, ?User $by = null, bool $notify = true): LeaveRequest
    {
        $byHr = $by && ! $by->isEmployee();
        $c = $this->check($employee, $data['leave_type'], $data['start_date'], $data['end_date'], count($documents ?? []), null, $byHr);

        $leave = DB::transaction(function () use ($employee, $data, $documents, $by, $byHr, $c) {
            // Lock the employee row so two requests sent at once can't both pass the balance check.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            return LeaveRequest::create([
                'employee_id'      => $employee->id,
                'leave_type'       => $c['type']->key,
                'company_email'    => $data['company_email'] ?? $employee->company_email ?? $employee->user?->email,
                'start_date'       => $c['start'],
                'end_date'         => $c['end'],
                'return_date'      => $c['return_date'],
                'days'             => $c['days'],
                'reason'           => $data['reason'] ?? null,
                'supervisor_email' => $data['supervisor_email'] ?? null,
                'documents'        => $documents ?: null,
                'status'           => 'pending',
                'stage'            => 'manager',
                'recorded_by'      => $byHr ? $by->id : null,
            ]);
        });

        Audit::record('leave.applied', $leave, $employee->full_name . ': ' . $c['type']->name . ', ' . $this->fmt($c['days']) . ' days from ' . $c['start']->format('j M Y')
            . ($byHr ? ' (entered by ' . $by->name . ')' : ''), [], $employee->company_id);

        if ($notify) {
            $this->notifyApprovers($leave, new LeaveApplied($leave), $data['supervisor_email'] ?? null);
        }

        return $leave;
    }

    /* ================================================================== */
    /*  Decide                                                              */
    /* ================================================================== */

    /** Can this staff member decide on this request? */
    public function canDecide(User $user, LeaveRequest $leave): bool
    {
        if (! $user->hasPermission('leave.approve') || ! $leave->isOpen()) {
            return false;
        }
        // Nobody approves their own leave (except the company administrator, who may have no one above them).
        if ($user->employee_id && (int) $user->employee_id === (int) $leave->employee_id && ! $user->isCompanyAdmin()) {
            return false;
        }
        // When HR confirmation is on, the second step belongs to HR.
        if ($leave->status === 'manager_approved' && ! $this->isHr($user)) {
            return false;
        }

        return $this->canSee($user, $leave->employee);
    }

    /** HR and administrators oversee everything; managers see their own team. */
    public function isHr(User $user): bool
    {
        return TeamScope::isHr($user);
    }

    public function canSee(User $user, ?Employee $employee): bool
    {
        return TeamScope::canSee($user, $employee);
    }

    /** Requests a staff member is allowed to see. */
    public function visibleQuery(User $user)
    {
        return TeamScope::restrict(LeaveRequest::query()->with(['employee', 'reviewedBy', 'managerApprovedBy', 'cancelledBy']), $user);
    }

    public function approve(LeaveRequest $leave, User $by, ?string $comment = null): LeaveRequest
    {
        $final = DB::transaction(function () use ($leave, $by, $comment) {
            $leave = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if (! $this->canDecide($by, $leave)) {
                throw ValidationException::withMessages(['leave' => $leave->isOpen() ? 'You can\'t approve this request.' : 'This request was already ' . strtolower($leave->statusLabel()) . '.']);
            }

            $company = Company::query()->find($leave->company_id);
            $needsHr = $company?->leave_hr_confirm && $leave->status === 'pending' && ! $this->isHr($by);
            if ($needsHr) {
                $leave->update(['status' => 'manager_approved', 'stage' => 'hr', 'manager_approved_by' => $by->id,
                    'manager_approved_at' => now(), 'manager_comment' => $comment]);
                Audit::record('leave.manager_approved', $leave, $leave->employee->full_name . ': manager approved, waiting for HR', [], $leave->company_id);

                return false;
            }

            $employee = Employee::query()->whereKey($leave->employee_id)->lockForUpdate()->firstOrFail();
            $type = $this->type($leave->leave_type, $leave->company_id);

            // Re-check the balance now (it may have changed since the request was made).
            $deduct = 0.0;
            if ($type?->deducts_balance && $employee->leave_days_balance !== null) {
                $deduct = (float) $leave->days;
                if (! $this->allowNegative($employee) && $deduct > (float) $employee->leave_days_balance + 0.001) {
                    throw ValidationException::withMessages(['leave' => $employee->full_name . ' only has ' . $this->fmt($employee->leave_days_balance) . ' days left, but this request is for ' . $this->fmt($deduct) . '.']);
                }
                $employee->forceFill(['leave_days_balance' => round((float) $employee->leave_days_balance - $deduct, 2)])->save();
            }

            $leave->update([
                'status' => 'approved', 'stage' => null, 'reviewed_by' => $by->id, 'reviewed_at' => now(),
                'hr_comment' => $comment, 'deducted_days' => $deduct,
                'manager_approved_by' => $leave->manager_approved_by ?? $by->id,
                'manager_approved_at' => $leave->manager_approved_at ?? now(),
            ]);

            if ($type?->deducts_balance) {
                $this->addToSheet($leave, $employee, +1);
            }
            $this->markAttendance($leave, $employee, $type, $by);

            Audit::record('leave.approved', $leave, $employee->full_name . ': ' . ($type?->name ?? $leave->leave_type) . ' approved, ' . $this->fmt($leave->days) . ' days'
                . ($deduct ? ' (balance now ' . $this->fmt($employee->leave_days_balance) . ')' : ''), [], $leave->company_id);

            return true;
        });

        $leave->refresh();
        if ($final) {
            $this->mailEmployee($leave, new LeaveDecided($leave));
        } else {
            $this->notifyApprovers($leave, new LeaveApplied($leave), null, hrOnly: true);
        }

        return $leave;
    }

    public function reject(LeaveRequest $leave, User $by, ?string $comment = null): LeaveRequest
    {
        DB::transaction(function () use (&$leave, $by, $comment) {
            $leave = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if (! $this->canDecide($by, $leave)) {
                throw ValidationException::withMessages(['leave' => $leave->isOpen() ? 'You can\'t decide on this request.' : 'This request was already ' . strtolower($leave->statusLabel()) . '.']);
            }
            $leave->update(['status' => 'rejected', 'stage' => null, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'hr_comment' => $comment]);
            Audit::record('leave.rejected', $leave, $leave->employee->full_name . ': leave request rejected' . ($comment ? " — {$comment}" : ''), [], $leave->company_id);
        });

        $this->mailEmployee($leave, new LeaveDecided($leave));

        return $leave;
    }

    /**
     * Cancel a request. Open requests: the employee or an approver. Approved leave:
     * HR at any time, or the employee before it starts. Everything approval did is undone.
     */
    public function cancel(LeaveRequest $leave, User $by, ?string $reason = null): LeaveRequest
    {
        $wasApproved = false;
        DB::transaction(function () use (&$leave, $by, $reason, &$wasApproved) {
            $leave = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $own = $by->employee_id && (int) $by->employee_id === (int) $leave->employee_id;

            if (! $this->canCancel($by, $leave)) {
                throw ValidationException::withMessages(['leave' => match (true) {
                    in_array($leave->status, ['rejected', 'cancelled'], true) => 'This request is already ' . $leave->status . '.',
                    $own && $leave->status === 'approved' => 'Your leave has already started. Please ask HR to change it.',
                    default => 'You can\'t cancel this request.',
                }]);
            }

            $wasApproved = $leave->status === 'approved';
            if ($wasApproved) {
                $this->reverse($leave);
            }

            $leave->update([
                'status' => 'cancelled', 'stage' => null, 'cancelled_at' => now(), 'cancelled_by' => $by->id,
                'cancelled_by_employee' => $own, 'cancel_reason' => $reason,
            ]);
            Audit::record('leave.cancelled', $leave, $leave->employee->full_name . ': ' . ($wasApproved ? 'approved ' : '') . 'leave cancelled'
                . ($own ? ' by the employee' : '') . ($wasApproved && (float) $leave->deducted_days > 0 ? ', ' . $this->fmt($leave->deducted_days) . ' days returned to the balance' : '')
                . ($reason ? " — {$reason}" : ''), [], $leave->company_id);
        });

        if ($wasApproved || ! ($by->employee_id && (int) $by->employee_id === (int) $leave->employee_id)) {
            $this->mailEmployee($leave, new LeaveDecided($leave));
        }

        return $leave;
    }

    public function canCancel(User $by, LeaveRequest $leave): bool
    {
        $own = $by->employee_id && (int) $by->employee_id === (int) $leave->employee_id;
        if ($leave->isOpen()) {
            return $own || ($by->hasPermission('leave.approve') && $this->canSee($by, $leave->employee));
        }
        if ($leave->status === 'approved') {
            return ($own && $leave->start_date->isAfter(today()))
                || ($by->hasPermission('leave.approve') && $this->isHr($by));
        }

        return false;
    }

    /* ================================================================== */
    /*  Effects of approval                                                 */
    /* ================================================================== */

    /** Add (or remove, $sign = -1) the working days to the leave sheet, month by month. */
    private function addToSheet(LeaveRequest $leave, Employee $employee, int $sign): void
    {
        $calc = new LeaveDayCalculator($employee);
        $perMonth = [];
        for ($d = $leave->start_date->copy(); $d->lte($leave->end_date); $d->addDay()) {
            if ($calc->isWorkingDay($d)) {
                $perMonth[$d->format('Y-n')] = ($perMonth[$d->format('Y-n')] ?? 0) + 1;
            }
        }
        // Keep the sheet consistent with the request total (e.g. half days).
        $sum = array_sum($perMonth);
        if ($sum > 0 && abs($sum - (float) $leave->days) > 0.001) {
            $lastKey = array_key_last($perMonth);
            $perMonth[$lastKey] += (float) $leave->days - $sum;
        }

        foreach ($perMonth as $ym => $days) {
            [$y, $m] = array_map('intval', explode('-', $ym));
            $record = LeaveRecord::query()->firstOrNew(['employee_id' => $employee->id, 'year' => $y, 'month' => $m]);
            $record->days_taken = max(0, round((float) ($record->days_taken ?? 0) + $sign * $days, 2));
            $record->save();
        }
    }

    private function markAttendance(LeaveRequest $leave, Employee $employee, ?LeaveType $type, ?User $by): void
    {
        $calc = new LeaveDayCalculator($employee);
        $status = $type?->attendanceStatus() ?? 'leave';
        $label = $type?->name ?? 'Leave';

        for ($d = $leave->start_date->copy(); $d->lte($leave->end_date); $d->addDay()) {
            if (! $calc->isWorkingDay($d)) {
                continue;
            }
            $existing = Attendance::query()->where('employee_id', $employee->id)->whereDate('date', $d)->first();
            // Never overwrite a day the person actually worked.
            if ($existing && $existing->status === 'present' && $existing->time_in) {
                continue;
            }
            $note = "leave#{$leave->id}: {$label}" . ($existing && $existing->status !== $status ? " (was {$existing->status})" : '');
            if ($existing) {
                $existing->forceFill(['status' => $status, 'note' => $note, 'marked_by' => $by?->id])->save();
            } else {
                Attendance::create(['employee_id' => $employee->id, 'date' => $d->toDateString(), 'status' => $status, 'note' => $note, 'marked_by' => $by?->id]);
            }
        }
    }

    /** Undo approval: balance, leave sheet, attendance. */
    private function reverse(LeaveRequest $leave): void
    {
        $employee = Employee::query()->whereKey($leave->employee_id)->lockForUpdate()->firstOrFail();
        $type = $this->type($leave->leave_type, $leave->company_id);

        if ((float) $leave->deducted_days > 0 && $employee->leave_days_balance !== null) {
            $employee->forceFill(['leave_days_balance' => round((float) $employee->leave_days_balance + (float) $leave->deducted_days, 2)])->save();
        }
        if ($type?->deducts_balance || (float) $leave->deducted_days > 0) {
            $this->addToSheet($leave, $employee, -1);
        }

        Attendance::query()->where('employee_id', $employee->id)->where('note', 'like', "leave#{$leave->id}:%")->get()
            ->each(function (Attendance $a) {
                if (preg_match('/\(was (\w+)\)$/', (string) $a->note, $m)) {
                    $a->forceFill(['status' => $m[1], 'note' => null])->save();
                } else {
                    $a->delete();
                }
            });
    }

    /* ================================================================== */

    private function allowNegative(Employee $employee): bool
    {
        return (bool) Company::query()->whereKey($employee->company_id)->value('leave_allow_negative');
    }

    /** Approvers: people with leave.approve who look after this employee (managers, HR, admins). */
    public function approverEmails(LeaveRequest $leave, bool $hrOnly = false): array
    {
        return $leave->employee ? TeamScope::approverEmails($leave->employee, 'leave.approve', $hrOnly) : [];
    }

    private function notifyApprovers(LeaveRequest $leave, $mailable, ?string $cc = null, bool $hrOnly = false): void
    {
        $to = $this->approverEmails($leave, $hrOnly);
        if (! $to) {
            return;
        }
        try {
            $mail = Mail::to($to);
            if ($cc) {
                $mail->cc($cc);
            }
            $mail->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function mailEmployee(LeaveRequest $leave, $mailable): void
    {
        $email = $leave->employee?->user?->email ?: $leave->employee?->company_email ?: $leave->company_email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    }
}
