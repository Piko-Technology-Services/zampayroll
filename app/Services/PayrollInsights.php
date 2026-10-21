<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything HR should know before a payroll goes for approval:
 *   fix   — will make someone's pay wrong or unpayable
 *   check — worth a second look (big changes, joiners/leavers, pending requests)
 *   info  — good to know (on leave, loan repayments, overtime included)
 */
class PayrollInsights
{
    public const CHANGE_THRESHOLD = 0.10; // flag take-home changes of 10% or more

    public const KINDS = [
        'no_pay'        => ['fix',   'bi-cash',               'No take-home pay set'],
        'zero_net'      => ['fix',   'bi-exclamation-octagon', 'Pays nothing'],
        'no_bank'       => ['fix',   'bi-bank',               'No bank account'],
        'dup_bank'      => ['fix',   'bi-files',              'Shared bank account'],
        'contract_over' => ['fix',   'bi-calendar-x',         'Contract already ended'],
        'changed'       => ['check', 'bi-graph-up-arrow',     'Big change from last month'],
        'joiner'        => ['check', 'bi-person-plus',        'Joined this month'],
        'leaver'        => ['check', 'bi-door-open',          'Leaving this month'],
        'dropped'       => ['check', 'bi-person-dash',        'Paid last month, not this month'],
        'no_tpin'       => ['check', 'bi-shield-exclamation', 'Missing TPIN / NAPSA / NHIMA'],
        'unpaid_leave'  => ['check', 'bi-calendar-minus',     'Unpaid leave'],
        'absent'        => ['check', 'bi-person-x',           'Absent days'],
        'pending'       => ['check', 'bi-hourglass-split',    'Request not decided'],
        'leave'         => ['info',  'bi-sun',                'On leave'],
        'loan'          => ['info',  'bi-piggy-bank',         'Loan repayment'],
        'overtime'      => ['info',  'bi-alarm',              'Overtime'],
        'new'           => ['info',  'bi-stars',              'First payroll'],
    ];

    /**
     * @return array{items: Collection, counts: array, chips: array, flags: array, previous: ?PayrollRun, prevNet: array}
     */
    public function forRun(PayrollRun $run): array
    {
        [$start, $end] = $this->monthRange($run);
        $payrolls = $run->payrolls()->with(['employee', 'items'])->get()->keyBy('employee_id');
        $active = Employee::where('employment_status', 'Active')->get()->keyBy('id');
        $items = collect();
        $add = function (string $kind, ?Employee $e, string $detail, array $extra = []) use ($items) {
            [$sev, $icon, $title] = self::KINDS[$kind];
            $items->push(array_merge([
                'kind' => $kind, 'severity' => $sev, 'icon' => $icon, 'title' => $title,
                'employee_id' => $e?->id, 'name' => $e?->full_name, 'detail' => $detail,
            ], $extra));
        };

        $previous = $this->previousRun($run);
        $prevNet = $previous ? $previous->payrolls()->pluck('net_pay', 'employee_id')->map(fn ($v) => (float) $v)->all() : [];

        // Bank accounts used by more than one person
        $accounts = $active->filter(fn ($e) => filled($e->bank_account_no))
            ->groupBy(fn ($e) => strtolower(trim($e->bank_name . '|' . preg_replace('/\s+/', '', $e->bank_account_no))))
            ->filter(fn ($g) => $g->count() > 1);
        foreach ($accounts as $group) {
            foreach ($group as $e) {
                $others = $group->where('id', '!=', $e->id)->map->full_name->implode(', ');
                $add('dup_bank', $e, "Same account as {$others}");
            }
        }

        foreach ($active as $e) {
            $p = $payrolls->get($e->id);

            if ((float) $e->net_salary <= 0) {
                $add('no_pay', $e, 'Skipped — add their take-home pay on their profile, then recalculate.', ['link' => route('employees.edit', [$e, 'step' => 'pay'])]);
            } elseif ($p && (float) $p->net_pay <= 0) {
                $add('zero_net', $e, 'Deductions are as big as their pay. Check loans and adjustments.');
            }

            if (blank($e->bank_account_no)) {
                $add('no_bank', $e, 'Can\'t be included in the bank payment file.', ['link' => route('employees.edit', [$e, 'step' => 'pay'])]);
            }

            if ($e->contract_end && $e->contract_end->lt($start)) {
                $add('contract_over', $e, 'Contract ended ' . $e->contract_end->format('j M Y') . ' but they are still Active.', ['link' => route('employees.edit', [$e, 'step' => 'job'])]);
            } elseif ($e->contract_end && $e->contract_end->between($start, $end)) {
                $add('leaver', $e, 'Contract ends ' . $e->contract_end->format('j M') . ' — a full month is being paid. Adjust if needed.');
            }

            if ($e->contract_start && $e->contract_start->between($start, $end) && $e->contract_start->day > 1) {
                $add('joiner', $e, 'Started ' . $e->contract_start->format('j M') . ' — a full month is being paid. Adjust if they should get part of a month.');
            }

            $missing = collect(['TPIN' => $e->tpin, 'NAPSA' => $e->ssn, 'NHIMA' => $e->nhima_no])->filter(fn ($v) => blank($v))->keys();
            if ($missing->isNotEmpty()) {
                $add('no_tpin', $e, 'Missing ' . $missing->implode(', ') . ' — needed for the statutory returns.', ['link' => route('employees.edit', [$e, 'step' => 'statutory'])]);
            }

            if ($p && $previous) {
                $before = $prevNet[$e->id] ?? null;
                if ($before === null) {
                    $add('new', $e, 'Not in ' . $previous->displayName() . '.');
                } elseif ($before > 0 && abs((float) $p->net_pay - $before) / $before >= self::CHANGE_THRESHOLD) {
                    $diff = (float) $p->net_pay - $before;
                    $add('changed', $e, sprintf('Take-home %s from K %s to K %s (%s%d%%).',
                        $diff > 0 ? 'up' : 'down', number_format($before, 2), number_format((float) $p->net_pay, 2),
                        $diff > 0 ? '+' : '−', (int) round(abs($diff) / $before * 100)), ['delta' => $diff]);
                }
            }
        }

        if ($previous) {
            $gone = array_diff(array_keys($prevNet), $payrolls->keys()->all());
            foreach (Employee::whereIn('id', $gone)->get() as $e) {
                $add('dropped', $e, 'Was paid in ' . $previous->displayName() . ' — now ' . strtolower($e->employment_status ?: 'inactive') . '.');
            }
        }

        // Leave this month
        $leave = LeaveRequest::with('employee')
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->get();
        foreach ($leave as $l) {
            if (! $l->employee) {
                continue;
            }
            $type = LeaveRequest::TYPES[$l->leave_type] ?? ucfirst((string) $l->leave_type ?: 'Leave');
            $when = $l->start_date->format('j M') . ' – ' . $l->end_date->format('j M') . ' · ' . $this->days($l->days) . ' day(s)';
            if ($l->status === 'pending') {
                $add('pending', $l->employee, "{$type} request ({$when}) is still pending.", ['link' => route('leave.dashboard')]);
            } elseif ($l->leave_type === 'unpaid') {
                $add('unpaid_leave', $l->employee, "{$when} — reduce their pay with a deduction if it isn't already.");
            } else {
                $add('leave', $l->employee, "{$type}, {$when}.");
            }
        }

        // Absences marked in attendance
        $absent = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'absent')->selectRaw('employee_id, COUNT(*) as n')->groupBy('employee_id')->pluck('n', 'employee_id');
        foreach ($absent as $id => $n) {
            if ($e = $active->get($id)) {
                $add('absent', $e, "Marked absent {$n} day(s) this month.", ['link' => route('attendance.calendar')]);
            }
        }

        // Loans: deductions on this run, and requests waiting for a decision
        $loanAdjustments = $run->adjustments()->whereNotNull('loan_id')->get()->groupBy('employee_id');
        foreach ($loanAdjustments as $empId => $adjs) {
            $loan = Loan::find($adjs->first()->loan_id);
            $add('loan', $active->get($empId) ?? Employee::find($empId), sprintf('K %s deducted · K %s left to repay.',
                number_format((float) $adjs->sum('value'), 2), number_format((float) ($loan?->balance ?? 0), 2)));
        }
        foreach (LoanRequest::with('employee')->where('status', 'pending')->get() as $lr) {
            if ($lr->employee) {
                $add('pending', $lr->employee, 'Loan request of K ' . number_format((float) $lr->amount, 2) . ' is waiting for a decision.', ['link' => route('loan.dashboard')]);
            }
        }

        // Overtime in this month
        $overtime = OvertimeRequest::with('employee')->whereIn('status', ['approved', 'pending'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
        $included = $run->adjustments()->whereNotNull('overtime_request_id')->pluck('overtime_request_id')->all();
        foreach ($overtime->groupBy('employee_id') as $group) {
            $e = $group->first()->employee;
            if (! $e) {
                continue;
            }
            $approved = $group->where('status', 'approved');
            $pending = $group->where('status', 'pending');
            if ($approved->isNotEmpty()) {
                $in = $approved->whereIn('id', $included);
                $detail = $this->hours($approved->sum('hours')) . ' approved · K ' . number_format((float) $approved->sum('amount'), 2);
                $detail .= $in->count() === $approved->count() ? ' — added to this payroll.' : ($run->include_overtime ? ' — some have no amount, add manually.' : ' — not added (overtime is switched off for this run).');
                $add('overtime', $e, $detail);
            }
            if ($pending->isNotEmpty()) {
                $add('pending', $e, $this->hours($pending->sum('hours')) . ' of overtime still pending — approve it before this run to include it.', ['link' => route('overtime.dashboard')]);
            }
        }

        $order = ['fix' => 0, 'check' => 1, 'info' => 2];
        $items = $items->sortBy(fn ($i) => $order[$i['severity']] . str_pad((string) array_search($i['kind'], array_keys(self::KINDS)), 2, '0', STR_PAD_LEFT) . $i['name'])->values();

        $flags = [];
        foreach ($items as $i) {
            if ($i['employee_id']) {
                $flags[$i['employee_id']][$i['kind']] = $i;
            }
        }

        return [
            'items'    => $items,
            'counts'   => ['fix' => $items->where('severity', 'fix')->count(), 'check' => $items->where('severity', 'check')->count(), 'info' => $items->where('severity', 'info')->count()],
            'chips'    => [
                'leave'    => $items->whereIn('kind', ['leave', 'unpaid_leave'])->pluck('employee_id')->unique()->count(),
                'loan'     => $items->where('kind', 'loan')->count(),
                'overtime' => $items->where('kind', 'overtime')->count(),
                'pending'  => $items->where('kind', 'pending')->count(),
                'changed'  => $items->where('kind', 'changed')->count(),
                'joiners'  => $items->whereIn('kind', ['joiner', 'new'])->count(),
            ],
            'flags'    => $flags,
            'previous' => $previous,
            'prevNet'  => $prevNet,
        ];
    }

    /** Quick numbers for the "start a payroll" screen, before anything is calculated. */
    public function preview(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $active = Employee::where('employment_status', 'Active');

        $ot = OvertimeRequest::where('status', 'approved')->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        return [
            'people'        => (clone $active)->count(),
            'no_pay'        => (clone $active)->where(fn ($q) => $q->whereNull('net_salary')->orWhere('net_salary', '<=', 0))->count(),
            'no_bank'       => (clone $active)->where(fn ($q) => $q->whereNull('bank_account_no')->orWhere('bank_account_no', ''))->count(),
            'on_leave'      => LeaveRequest::where('status', 'approved')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->distinct('employee_id')->count('employee_id'),
            'loans'         => Loan::where('status', 'active')->distinct('employee_id')->count('employee_id'),
            'overtime'      => (clone $ot)->distinct('employee_id')->count('employee_id'),
            'overtime_amt'  => (float) (clone $ot)->sum('amount'),
            'pending'       => LeaveRequest::whereIn('status', LeaveRequest::OPEN)->count() + OvertimeRequest::where('status', 'pending')->count() + LoanRequest::where('status', 'pending')->count(),
            'expected_net'  => (float) (clone $active)->sum('net_salary'),
        ];
    }

    public function previousRun(PayrollRun $run): ?PayrollRun
    {
        $date = $run->period_date;

        return PayrollRun::query()
            ->where('id', '!=', $run->id)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID, 'Finalized', 'Finalised'])
            ->get()
            ->filter(fn ($r) => $r->period_date && $date && $r->period_date->lt($date))
            ->sortByDesc(fn ($r) => $r->period_date)
            ->first();
    }

    private function monthRange(PayrollRun $run): array
    {
        $d = $run->period_date ?? now()->startOfMonth();

        return [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()];
    }

    private function days($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    }

    private function hours($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1), '0'), '.') . 'h';
    }
}
