<?php

namespace App\Services\Overtime;

use App\Mail\OvertimeApplied;
use App\Mail\OvertimeDecided;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\User;
use App\Services\LeaveDayCalculator;
use App\Support\Audit;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * One set of overtime rules for the portal, public form, HR and AI mode:
 * hours (with breaks and overnight shifts), company-set rates, approval,
 * cancellation and getting it into payroll exactly once.
 */
class OvertimeService
{
    /** Company defaults — every value can be changed in Overtime → Rates & rules. */
    public const DEFAULTS = [
        'rates' => [
            'normal' => ['label' => 'Normal overtime', 'hint' => 'Extra hours on a working day', 'multiplier' => 1.5],
            'double' => ['label' => 'Double overtime', 'hint' => 'Rest day or public holiday', 'multiplier' => 2.0],
        ],
        'base' => 'basic',          // basic = basic salary; gross = basic + regular allowances
        'hours_per_day' => 8,       // hourly rate = daily rate ÷ this
        'max_hours_per_day' => 12,  // one claim can't be longer than this
        'require_reason' => true,
        'days_back' => 60,          // how old a claim may be when an employee logs it
    ];

    /* ================================================================== */
    /*  Settings                                                            */
    /* ================================================================== */

    public function settings(?int $companyId = null): array
    {
        $companyId ??= \App\Support\Tenancy::companyId();
        $saved = $companyId ? (Company::query()->whereKey($companyId)->value('overtime_settings') ?? []) : [];
        if (is_string($saved)) {
            $saved = json_decode($saved, true) ?: [];
        }
        $s = array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
        if (empty($s['rates'])) {
            $s['rates'] = self::DEFAULTS['rates'];
        }

        return $s;
    }

    public function saveSettings(Company $company, array $data, User $by): void
    {
        $before = $this->settings($company->id);
        $company->forceFill(['overtime_settings' => $data])->save();
        Audit::record('overtime.settings_updated', $company, 'Overtime rates & rules changed', ['from' => $before, 'to' => $data], $company->id);
    }

    /** [key => [label, hint, multiplier]] — same shape as the old OvertimeRequest::TYPES. */
    public function types(?int $companyId = null): array
    {
        return collect($this->settings($companyId)['rates'])->map(fn ($r) => [
            'label' => $r['label'], 'tooltip' => $r['hint'] ?? '', 'hint' => $r['hint'] ?? '', 'multiplier' => (float) $r['multiplier'],
        ])->all();
    }

    /* ================================================================== */
    /*  Maths                                                               */
    /* ================================================================== */

    /** Daily rate used for overtime, from the company's chosen base. */
    public function dailyRate(Employee $employee, ?array $settings = null): float
    {
        $settings ??= $this->settings($employee->company_id);
        $days = max(1, (int) $employee->working_days_per_month);
        if ($settings['base'] === 'gross' && (float) $employee->daily_rate_salary > 0) {
            return round((float) $employee->daily_rate_salary, 2);
        }

        return round(((float) ($employee->salary ?? 0)) / $days, 2);
    }

    /**
     * Work out hours and pay, and check the rules. Throws ValidationException.
     *
     * @return array{hours: float, minutes: int, overnight: bool, multiplier: float, daily_rate: float, hourly_rate: float, amount: float, type: string, suggested: ?string, warnings: array}
     */
    public function check(Employee $employee, array $in, ?int $ignoreId = null, bool $byHr = false): array
    {
        $s = $this->settings($employee->company_id);
        $types = $this->types($employee->company_id);
        $errors = [];

        $type = (string) ($in['type'] ?? '');
        if (! isset($types[$type])) {
            $errors['type'] = 'Choose one of the overtime rates.';
        }
        try {
            $date = Carbon::parse($in['date'])->startOfDay();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['date' => 'Enter the date you worked.']);
        }
        if ($date->isAfter(today()->addDays(7))) {
            $errors['date'] = 'Overtime can only be logged once it is worked (or for this coming week).';
        }
        if (! $byHr && $date->lt(today()->subDays((int) $s['days_back']))) {
            $errors['date'] = 'That\'s more than ' . $s['days_back'] . ' days ago. Please ask HR to enter it.';
        }

        $start = $this->time($in['start_time'] ?? null);
        $end = $this->time($in['end_time'] ?? null);
        if (! $start || ! $end) {
            throw ValidationException::withMessages(['end_time' => 'Enter the start and end times.'] + $errors);
        }
        $startAt = $date->copy()->setTimeFromTimeString($start);
        $endAt = $date->copy()->setTimeFromTimeString($end);
        $overnight = false;
        if ($endAt->lte($startAt)) {
            $endAt->addDay(); // e.g. 22:00 → 02:00
            $overnight = true;
        }
        $span = (int) $startAt->diffInMinutes($endAt, true);
        $break = max(0, (int) ($in['break_minutes'] ?? 0));
        if ($break >= $span) {
            $errors['break_minutes'] = 'The break can\'t be as long as the whole time worked.';
        }
        $minutes = $span - $break;
        $hours = round($minutes / 60, 2);
        if ($hours > (float) $s['max_hours_per_day']) {
            $errors['end_time'] = 'That\'s ' . $this->fmt($hours) . ' hours — more than the ' . $this->fmt($s['max_hours_per_day']) . ' hours allowed in one claim.';
        }
        if ($s['require_reason'] && trim((string) ($in['comment'] ?? '')) === '') {
            $errors['comment'] = 'Say briefly what the overtime was for.';
        }

        // Same person, overlapping times (including a shift that ran past midnight).
        $clash = OvertimeRequest::query()->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereBetween('date', [$date->copy()->subDay()->toDateString(), $date->copy()->addDay()->toDateString()])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->get()
            ->first(function (OvertimeRequest $o) use ($startAt, $endAt) {
                $oStart = $o->date->copy()->setTimeFromTimeString(substr((string) $o->start_time, 0, 5));
                $oEnd = $o->date->copy()->setTimeFromTimeString(substr((string) $o->end_time, 0, 5));
                if ($oEnd->lte($oStart)) {
                    $oEnd->addDay();
                }

                return $oStart->lt($endAt) && $startAt->lt($oEnd);
            });
        if ($clash) {
            $errors['start_time'] = 'Overtime from ' . substr($clash->start_time, 0, 5) . ' to ' . substr($clash->end_time, 0, 5) . ' on ' . $clash->date->format('j M') . ' is already logged and overlaps this.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $warnings = [];
        $calc = new LeaveDayCalculator($employee);
        $suggested = $calc->isWorkingDay($date) ? null : collect($types)->sortByDesc('multiplier')->keys()->first();
        if ($suggested && $suggested !== $type && $types[$type]['multiplier'] < $types[$suggested]['multiplier']) {
            $warnings[] = $date->format('l j M') . ' is a rest day or public holiday — the ' . $types[$suggested]['label'] . ' rate may apply.';
        }
        if (LeaveRequest::query()->where('employee_id', $employee->id)->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
            $warnings[] = $employee->first_name . ' is on approved leave that day.';
        }

        $daily = $this->dailyRate($employee, $s);
        $hourly = round($daily / max(1, (float) $s['hours_per_day']), 4);
        $multiplier = (float) $types[$type]['multiplier'];

        return [
            'hours' => $hours, 'minutes' => $minutes, 'overnight' => $overnight, 'multiplier' => $multiplier,
            'daily_rate' => $daily, 'hourly_rate' => round($hourly, 2), 'amount' => round($hourly * $multiplier * $hours, 2),
            'type' => $type, 'suggested' => $suggested, 'warnings' => $warnings, 'base' => $s['base'],
            'start' => $start, 'end' => $end, 'date' => $date,
        ];
    }

    /* ================================================================== */
    /*  Apply / decide / cancel                                              */
    /* ================================================================== */

    public function apply(Employee $employee, array $in, ?User $by = null, bool $notify = true): OvertimeRequest
    {
        $byHr = $by && ! $by->isEmployee();
        $c = $this->check($employee, $in, null, $byHr);

        $ot = DB::transaction(function () use ($employee, $in, $by, $byHr, $c) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            // Re-check overlaps inside the lock (two quick submissions).
            $this->check($employee, $in, null, $byHr);

            return OvertimeRequest::create([
                'employee_id'     => $employee->id,
                'company_email'   => $in['company_email'] ?? $employee->company_email ?? $employee->user?->email ?? '',
                'date'            => $c['date']->toDateString(),
                'start_time'      => $c['start'],
                'end_time'        => $c['end'],
                'break_minutes'   => (int) ($in['break_minutes'] ?? 0),
                'hours'           => $c['hours'],
                'type'            => $c['type'],
                'rate_multiplier' => $c['multiplier'],
                'daily_rate'      => $c['daily_rate'],
                'hourly_rate'     => $c['hourly_rate'],
                'rate_base'       => $c['base'],
                'amount'          => $c['amount'],
                'comment'         => $in['comment'] ?? null,
                'status'          => 'pending',
                'recorded_by'     => $byHr ? $by->id : null,
            ]);
        });

        Audit::record('overtime.logged', $ot, $employee->full_name . ': ' . $this->fmt($c['hours']) . ' hours on ' . $c['date']->format('j M Y') . ' (K ' . number_format($c['amount'], 2) . ')'
            . ($byHr ? ' — entered by ' . $by->name : ''), [], $employee->company_id);

        if ($notify) {
            $this->notify(TeamScope::approverEmails($employee, 'overtime.approve'), new OvertimeApplied($ot));
        }

        return $ot;
    }

    public function canDecide(User $user, OvertimeRequest $ot): bool
    {
        return $ot->status === 'pending' && $user->hasPermission('overtime.approve')
            && ! ($user->employee_id && (int) $user->employee_id === (int) $ot->employee_id && ! $user->isCompanyAdmin())
            && TeamScope::canSee($user, $ot->employee);
    }

    /** Approve, optionally correcting the hours (the amount is worked out again). */
    public function approve(OvertimeRequest $ot, User $by, ?string $comment = null, ?float $hours = null): OvertimeRequest
    {
        DB::transaction(function () use (&$ot, $by, $comment, $hours) {
            $ot = OvertimeRequest::query()->whereKey($ot->id)->lockForUpdate()->firstOrFail();
            if (! $this->canDecide($by, $ot)) {
                throw ValidationException::withMessages(['overtime' => $ot->status === 'pending' ? 'You can\'t approve this overtime.' : 'This overtime was already ' . strtolower($ot->statusLabel()) . '.']);
            }
            $changes = [];
            if ($hours !== null && abs($hours - (float) $ot->hours) > 0.001) {
                if ($hours <= 0 || $hours > (float) $ot->hours) {
                    throw ValidationException::withMessages(['hours' => 'Approved hours must be more than 0 and not more than the ' . $this->fmt($ot->hours) . ' hours claimed.']);
                }
                $changes = ['hours' => ['from' => (float) $ot->hours, 'to' => $hours]];
                $ot->requested_hours = $ot->hours;
                $ot->hours = $hours;
                $ot->amount = round((float) ($ot->hourly_rate ?: ((float) $ot->daily_rate / 8)) * (float) $ot->rate_multiplier * $hours, 2);
            }
            $ot->forceFill(['status' => 'approved', 'hr_comment' => $comment, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            Audit::record('overtime.approved', $ot, $ot->employee->full_name . ': ' . $this->fmt($ot->hours) . ' hours on ' . $ot->date->format('j M') . ' approved (K ' . number_format((float) $ot->amount, 2) . ')', $changes, $ot->company_id);
        });

        $this->mailEmployee($ot, new OvertimeDecided($ot));

        return $ot;
    }

    public function reject(OvertimeRequest $ot, User $by, ?string $comment = null): OvertimeRequest
    {
        DB::transaction(function () use (&$ot, $by, $comment) {
            $ot = OvertimeRequest::query()->whereKey($ot->id)->lockForUpdate()->firstOrFail();
            if (! $this->canDecide($by, $ot)) {
                throw ValidationException::withMessages(['overtime' => 'This overtime was already ' . strtolower($ot->statusLabel()) . '.']);
            }
            $ot->forceFill(['status' => 'rejected', 'hr_comment' => $comment, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            Audit::record('overtime.rejected', $ot, $ot->employee->full_name . ': overtime on ' . $ot->date->format('j M') . ' rejected' . ($comment ? " — {$comment}" : ''), [], $ot->company_id);
        });

        $this->mailEmployee($ot, new OvertimeDecided($ot));

        return $ot;
    }

    public function canCancel(User $by, OvertimeRequest $ot): bool
    {
        $own = $by->employee_id && (int) $by->employee_id === (int) $ot->employee_id;
        if ($ot->status === 'pending') {
            return $own || ($by->hasPermission('overtime.approve') && TeamScope::canSee($by, $ot->employee));
        }

        return $ot->status === 'approved' && ! $ot->isLockedInPayroll() && $by->hasPermission('overtime.approve') && TeamScope::isHr($by);
    }

    public function cancel(OvertimeRequest $ot, User $by, ?string $reason = null): OvertimeRequest
    {
        $wasApproved = false;
        DB::transaction(function () use (&$ot, $by, $reason, &$wasApproved) {
            $ot = OvertimeRequest::query()->whereKey($ot->id)->lockForUpdate()->firstOrFail();
            if (! $this->canCancel($by, $ot)) {
                throw ValidationException::withMessages(['overtime' => $ot->status === 'approved' && $ot->isLockedInPayroll()
                    ? 'This overtime was paid in a payroll that is already approved, so it can\'t be cancelled. Add a deduction in the next payroll instead.'
                    : 'You can\'t cancel this overtime.']);
            }
            $wasApproved = $ot->status === 'approved';
            // Take it out of a payroll that is still being prepared (recalculate to update the payslip).
            $removedFrom = null;
            if ($wasApproved && $ot->payroll_run_id) {
                PayrollRunAdjustment::query()->where('overtime_request_id', $ot->id)->delete();
                $removedFrom = $ot->payroll_run_id;
            }
            $ot->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason, 'payroll_run_id' => null])->save();
            Audit::record('overtime.cancelled', $ot, $ot->employee->full_name . ': ' . ($wasApproved ? 'approved ' : '') . 'overtime on ' . $ot->date->format('j M') . ' cancelled'
                . ($removedFrom ? ' and taken out of the payroll being prepared (recalculate it)' : '') . ($reason ? " — {$reason}" : ''), [], $ot->company_id);
        });

        if (! ($by->employee_id && (int) $by->employee_id === (int) $ot->employee_id)) {
            $this->mailEmployee($ot, new OvertimeDecided($ot));
        }

        return $ot;
    }

    /* ================================================================== */
    /*  Payroll                                                             */
    /* ================================================================== */

    /**
     * Put approved, unpaid overtime into a payroll that is being prepared.
     * Includes late approvals from earlier months, never anything already paid in
     * another (non-deleted) payroll. Safe to run again: it rewrites its own lines.
     */
    public function syncIntoPayroll(PayrollRun $run, int $employeeId): void
    {
        $previous = PayrollRunAdjustment::query()->where('payroll_run_id', $run->id)->where('employee_id', $employeeId)
            ->whereNotNull('overtime_request_id')->pluck('overtime_request_id')->all();
        PayrollRunAdjustment::query()->where('payroll_run_id', $run->id)->where('employee_id', $employeeId)->whereNotNull('overtime_request_id')->delete();

        $included = [];
        // include_overtime defaults to on in the database (null here means a fresh, unsaved-default run).
        if ($run->include_overtime !== false && $run->period_date) {
            $periodStart = $run->period_date->copy()->startOfMonth();
            $periodEnd = $run->period_date->copy()->endOfMonth();

            // Overtime already in another payroll that still exists.
            $paidElsewhere = PayrollRunAdjustment::query()->whereNotNull('overtime_request_id')->where('payroll_run_id', '!=', $run->id)
                ->where('employee_id', $employeeId)->whereIn('payroll_run_id', PayrollRun::query()->select('id'))->pluck('overtime_request_id')->all();

            $items = OvertimeRequest::query()->where('employee_id', $employeeId)->where('status', 'approved')
                ->whereDate('date', '<=', $periodEnd)->whereDate('date', '>=', $periodStart->copy()->subMonthsNoOverflow(3))
                ->whereNotIn('id', $paidElsewhere ?: [0])
                ->orderBy('date')->get();

            foreach ($items as $ot) {
                if ((float) $ot->amount <= 0) {
                    continue;
                }
                $late = $ot->date->lt($periodStart) ? ', from ' . $ot->date->format('F') : '';
                PayrollRunAdjustment::create([
                    'payroll_run_id'      => $run->id,
                    'employee_id'         => $employeeId,
                    'overtime_request_id' => $ot->id,
                    'name'                => 'Overtime · ' . $ot->date->format('j M') . ' (' . $this->fmt($ot->hours) . 'h' . $late . ')',
                    'type'                => 'earning',
                    'formula_type'        => 'fixed',
                    'value'               => $ot->amount,
                    'tax_profile'         => 'taxable',
                    'active'              => true,
                ]);
                $included[] = $ot->id;
            }
        }

        if ($included) {
            OvertimeRequest::query()->whereIn('id', $included)->update(['payroll_run_id' => $run->id]);
        }
        $released = array_diff($previous, $included);
        if ($released) {
            OvertimeRequest::query()->whereIn('id', $released)->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null]);
        }
    }

    /* ================================================================== */

    public function visibleQuery(User $user)
    {
        return TeamScope::restrict(OvertimeRequest::query()->with(['employee', 'reviewedBy', 'payrollRun', 'cancelledBy']), $user);
    }

    private function time($v): ?string
    {
        $v = trim((string) $v);

        return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:\d\d)?$/', $v, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
    }

    private function notify(array $to, $mailable): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function mailEmployee(OvertimeRequest $ot, $mailable): void
    {
        $email = $ot->employee?->user?->email ?: $ot->employee?->company_email ?: $ot->company_email;
        if ($email) {
            $this->notify([$email], $mailable);
        }
    }

    public function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    }
}
