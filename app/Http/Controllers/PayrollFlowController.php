<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\HrActions;
use App\Services\PayrollApprovals;
use App\Services\PayrollInsights;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The guided payroll journey:
 *   1 Start (pick the month, we calculate everyone)
 *   2 Check (highlights: anomalies, leave, loans, overtime)
 *   3 Submit for approval
 *   4 Approval chain (set by the admin) → approved & locked
 *   5 Pay (payslips, bank file, returns, mark as paid)
 */
class PayrollFlowController extends Controller
{
    public function __construct(private PayrollApprovals $approvals, private PayrollInsights $insights)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  Home: this month, waiting for you, history                          */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $user = $request->user();
        $runs = PayrollRun::visible()->withCount('payrolls')->withSum('payrolls', 'net_pay')->with(['creator', 'finalizedBy'])->get()
            ->sortByDesc(fn ($r) => sprintf('%012d%010d', $r->period_date?->timestamp ?? 0, $r->id))->values();

        $thisMonth = now()->format('Y-m');
        $monthRuns = $runs->filter(fn ($r) => $r->period_date && $r->period_date->format('Y-m') === $thisMonth)->sortBy('id')->values();
        // The run that needs attention first: one being prepared, then one in approval, then the latest.
        $current = $monthRuns->first(fn ($r) => $r->isEditable()) ?? $monthRuns->first(fn ($r) => $r->isInReview()) ?? $monthRuns->last();
        $waiting = $runs->filter(fn ($r) => $r->isInReview() && $this->approvals->canApprove($user, $r))->values();
        $inReview = $runs->filter(fn ($r) => $r->isInReview())->values();

        $year = (int) $request->query('year', 0);
        $status = (string) $request->query('status', '');
        $history = $runs->when($year, fn ($c) => $c->filter(fn ($r) => $r->period_date?->year === $year))
            ->when($status, fn ($c) => $c->filter(fn ($r) => $r->statusTone() === $status))->values();
        $years = $runs->map(fn ($r) => $r->period_date?->year)->filter()->unique()->sortDesc()->values();
        $months = $history->groupBy(fn ($r) => $r->period_date?->format('Y-m') ?? $r->period)->map(fn ($g) => $g->sortBy('id')->values());

        return view('dashboard.payroll.flow.index', [
            'current'      => $current,
            'monthRuns'    => $monthRuns,
            'currentStep'  => $current ? $this->approvals->currentStep($current) : null,
            'waiting'      => $waiting,
            'inReview'     => $inReview,
            'months'       => $months,
            'counts'       => $runs->groupBy(fn ($r) => $r->statusTone())->map->count(),
            'years'        => $years,
            'year'         => $year,
            'status'       => $status,
            'preview'      => $current ? null : $this->insights->preview(now()),
            'hiddenCount'  => PayrollRun::hiddenOnly()->count(),
            'trashedCount' => PayrollRun::onlyTrashed()->count(),
            'approvals'    => $this->approvals,
            'chain'        => $this->approvals->chain(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Step 1 — start                                                      */
    /* ------------------------------------------------------------------ */

    public function create(Request $request)
    {
        $month = $this->month($request->query('month')) ?? now()->startOfMonth();
        $all = PayrollRun::withCount('payrolls')->get()->filter(fn ($r) => $r->period_date);
        $byMonth = $all->groupBy(fn ($r) => $r->period_date->format('Y-m'));

        $months = collect(range(-3, 2))->map(fn ($i) => now()->startOfMonth()->addMonths($i))
            ->map(function ($m) use ($byMonth) {
                $runs = $byMonth->get($m->format('Y-m'), collect());

                return ['value' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'runs' => $runs,
                    'note' => $runs->isEmpty() ? 'Not started' : ($runs->count() === 1 ? $runs->first()->statusLabel() : $runs->count() . ' pay runs')];
            });

        $inMonth = $byMonth->get($month->format('Y-m'), collect())->sortBy('id')->values();
        $continue = $request->filled('run')
            ? $inMonth->firstWhere('id', (int) $request->query('run'))
            : ($request->query('new') ? null : $inMonth->first(fn ($r) => $r->isEditable()));
        if ($continue && ! $continue->isEditable()) {
            $continue = null;
        }

        $employees = Employee::where('employment_status', 'Active')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'department', 'employee_id', 'net_salary']);
        $lockedIds = \App\Models\Payroll::whereIn('payroll_run_id', $inMonth->filter(fn ($r) => $r->isLocked())->pluck('id'))->pluck('employee_id')->unique();

        return view('dashboard.payroll.flow.start', [
            'month'       => $month,
            'months'      => $months,
            'inMonth'     => $inMonth,
            'continue'    => $continue,
            'employees'   => $employees,
            'departments' => $employees->groupBy(fn ($e) => $e->department ?: 'No department')->map->count()->sortKeys(),
            'alreadyPaid' => $lockedIds->count(),
            'preview'     => $this->insights->preview($month),
            'chain'       => $this->approvals->chain(),
            'approvals'   => $this->approvals,
            'types'       => PayrollRun::TYPES,
        ]);
    }

    public function store(Request $request, HrActions $hr)
    {
        $data = $request->validate([
            'period'           => 'required|date_format:Y-m',
            'alias'            => 'nullable|string|max:120',
            'include_overtime' => 'nullable|boolean',
            'run_id'           => 'nullable|integer',
            'new_run'          => 'nullable|boolean',
            'run_type'         => 'nullable|in:' . implode(',', array_keys(PayrollRun::TYPES)),
            'coverage'         => 'nullable|in:all,departments,people',
            'departments'      => 'required_if:coverage,departments|array',
            'departments.*'    => 'string|max:120',
            'employees'        => 'required_if:coverage,people|array',
            'employees.*'      => 'integer',
        ], [
            'departments.required_if' => 'Pick at least one department.',
            'employees.required_if'   => 'Pick at least one person.',
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['period'])->startOfMonth();
        $coverage = $data['coverage'] ?? 'all';
        $scope = match ($coverage) {
            'departments' => ['mode' => 'departments', 'departments' => array_values(array_unique($data['departments']))],
            'people'      => ['mode' => 'people', 'employees' => array_values(array_unique(array_map('intval', $data['employees'])))],
            default       => null,
        };

        // Without an explicit "new run", carry on with the month's run that's still being prepared (avoids double-submits).
        if (empty($data['run_id']) && ! $request->boolean('new_run')) {
            $data['run_id'] = PayrollRun::where('period', $data['period'])->get()->first(fn ($r) => $r->isEditable())?->id;
        }

        if (! empty($data['run_id'])) {
            $run = PayrollRun::findOrFail($data['run_id']);
            if (! $run->isEditable()) {
                return redirect()->route('payroll.runs.show', $run)
                    ->with('info', $run->displayName() . ' is ' . strtolower($run->statusLabel()) . '. Start a new pay run instead, or unlock it.');
            }
        } else {
            $count = PayrollRun::where('period', $data['period'])->count();
            $type = $data['run_type'] ?? 'regular';
            $run = new PayrollRun([
                'period'     => $data['period'],
                'alias'      => $month->format('F Y') . ($count || $type !== 'regular' ? ' · ' . PayrollRun::TYPES[$type] . ($count ? ' #' . ($count + 1) : '') : ' payroll'),
                'created_by' => $request->user()->id,
                'status'     => PayrollRun::DRAFT,
            ]);
        }

        $run->fill([
            'run_type'         => $data['run_type'] ?? $run->run_type ?? 'regular',
            'scope'            => $scope,
            'include_overtime' => $request->boolean('include_overtime', true),
        ] + (filled($data['alias'] ?? null) ? ['alias' => $data['alias']] : []));

        if ($run->employeesQuery()->count() === 0) {
            return back()->withInput()->with('error', 'Nobody active matches who you picked for this pay run.');
        }

        $run->save();
        $hr->generatePayroll($run, $request->user());

        return redirect()->route('payroll.runs.show', $run)
            ->with('success', "Done — {$run->payrolls()->count()} people calculated. Check the highlights, then submit for approval.")
            ->with('celebrate', true);
    }

    /* ------------------------------------------------------------------ */
    /*  Steps 2–5 — the run workspace                                       */
    /* ------------------------------------------------------------------ */

    public function show(Request $request, PayrollRun $run)
    {
        $run->load(['payrolls.employee', 'payrolls.items', 'events.user', 'creator', 'submitter', 'finalizedBy']);
        $user = $request->user();
        $insights = $this->insights->forRun($run);
        $totals = $this->approvals->totals($run);
        $previous = $insights['previous'];
        $prevTotals = $previous ? $this->approvals->totals($previous) : null;

        $rows = $run->payrolls->sortBy(fn ($p) => $p->employee?->full_name)->values()->map(function ($p) use ($insights) {
            $items = $p->items;
            $code = fn ($c) => (float) $items->where('code', $c)->sum('amount');

            return [
                'payroll'  => $p,
                'employee' => $p->employee,
                'gross'    => (float) $p->total_income,
                'paye'     => $code('D00'),
                'napsa'    => $code('D01'),
                'nhima'    => $code('D02'),
                'other'    => (float) $p->total_deductions - $code('D00') - $code('D01') - $code('D02'),
                'net'      => (float) $p->net_pay,
                'prev'     => $insights['prevNet'][$p->employee_id] ?? null,
                'flags'    => $insights['flags'][$p->employee_id] ?? [],
                'earnings' => $items->where('type', 'earning')->where('amount', '!=', 0)->values(),
                'deductions' => $items->where('type', 'deduction')->where('amount', '!=', 0)->values(),
            ];
        });

        $chain = $this->approvals->chainFor($run);
        $done = $run->events->where('action', 'approved');
        // Only approvals since the latest submission count toward the current pass.
        $lastSubmit = $run->events->where('action', 'submitted')->last();
        if ($lastSubmit) {
            $done = $done->where('id', '>', $lastSubmit->id);
        }
        $steps = collect($chain)->map(function ($s) use ($run, $done) {
            $ev = $done->firstWhere('step_position', $s['position']);
            $state = $ev ? 'done' : ($run->isInReview() && (int) $run->current_step === $s['position'] ? 'current' : ($run->isLocked() ? 'done' : 'todo'));

            return $s + [
                'who'    => $this->approvals->stepWho($s),
                'state'  => $state,
                'event'  => $ev,
                'people' => $this->approvals->approversFor($s)->pluck('name'),
            ];
        });

        return view('dashboard.payroll.flow.run', [
            'run'        => $run,
            'rows'       => $rows,
            'insights'   => $insights,
            'totals'     => $totals,
            'prevTotals' => $prevTotals,
            'previous'   => $previous,
            'steps'      => $steps,
            'stage'      => $run->stage(),
            'canPrepare' => $user->hasPermission('payroll.run'),
            'canEdit'    => $run->isEditable() && $user->hasPermission('payroll.run'),
            'canApprove' => $this->approvals->canApprove($user, $run),
            'canUnlock'  => $run->isLocked() && $user->hasPermission('payroll.finalize'),
            'whyNot'     => $run->isInReview() ? $this->approvals->whyCantApprove($user, $run) : null,
            'current'    => $this->approvals->currentStep($run),
        ]);
    }

    public function submit(Request $request, PayrollRun $run)
    {
        $insights = $this->insights->forRun($run);
        $request->validate([
            'comment'      => 'nullable|string|max:1000',
            'acknowledged' => $insights['counts']['fix'] > 0 ? 'accepted' : 'nullable',
        ], ['acknowledged.accepted' => 'Tick the box to confirm you\'ve looked at the items that need fixing.']);

        return $this->attempt(fn () => $this->approvals->submit($run, $request->user(), $request->input('comment')),
            $run, 'Submitted for approval. We\'ve let the first approver know.');
    }

    public function approve(Request $request, PayrollRun $run)
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        return $this->attempt(function () use ($request, $run) {
            $this->approvals->approve($run, $request->user(), $request->input('comment'));
        }, $run, fn () => $run->fresh()->isLocked() ? '🔒 Approved and locked. Payslips and the bank file are ready.' : 'Approved. It has moved to the next approver.', true);
    }

    public function sendBack(Request $request, PayrollRun $run)
    {
        $request->validate(['comment' => 'required|string|max:1000'], ['comment.required' => 'Say what needs changing so the preparer knows what to fix.']);

        return $this->attempt(fn () => $this->approvals->sendBack($run, $request->user(), $request->input('comment')),
            $run, 'Sent back with your comments.');
    }

    public function recall(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->approvals->recall($run, $request->user(), $request->input('comment')),
            $run, 'Recalled to draft. Make your changes and submit again.');
    }

    public function markPaid(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->approvals->markPaid($run, $request->user(), $request->input('comment')),
            $run, 'Marked as paid. Well done! 🎉', true);
    }

    /** Re-open an approved/paid run for corrections (approvers only; reason required, audited). */
    public function unlock(Request $request, PayrollRun $run)
    {
        $request->validate(['reason' => 'required|string|min:5|max:1000'], [
            'reason.required' => 'Say why it is being unlocked — this goes into the audit trail.',
            'reason.min'      => 'Say a little more about why it is being unlocked.',
        ]);

        return $this->attempt(fn () => $this->approvals->unlock($run, $request->user(), $request->input('reason')),
            $run, 'Unlocked. Make your corrections, recalculate, then submit it for approval again.');
    }

    /** Switch approved overtime on/off for this run and recalculate. */
    public function overtime(Request $request, PayrollRun $run, HrActions $hr)
    {
        if (! $run->isEditable()) {
            return back()->with('error', 'This payroll is in approval or locked.');
        }
        $run->update(['include_overtime' => $request->boolean('include_overtime')]);
        $hr->generatePayroll($run, $request->user());

        return back()->with('success', $run->include_overtime ? 'Approved overtime added and pay recalculated.' : 'Overtime removed and pay recalculated.');
    }

    /* ------------------------------------------------------------------ */

    private function attempt(callable $fn, PayrollRun $run, $message, bool $celebrate = false)
    {
        try {
            $fn();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $r = redirect()->route('payroll.runs.show', $run)->with('success', is_callable($message) ? $message() : $message);

        return $celebrate ? $r->with('celebrate', true) : $r;
    }

    private function month(?string $v): ?Carbon
    {
        try {
            return $v ? Carbon::createFromFormat('Y-m', $v)->startOfMonth() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
