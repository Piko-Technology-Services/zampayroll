<?php

namespace App\Http\Controllers;

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
        $current = $runs->first(fn ($r) => $r->period_date && $r->period_date->format('Y-m') === $thisMonth);
        $waiting = $runs->filter(fn ($r) => $r->isInReview() && $this->approvals->canApprove($user, $r))->values();
        $inReview = $runs->filter(fn ($r) => $r->isInReview())->values();

        $year = (int) $request->query('year', 0);
        $history = $runs->when($year, fn ($c) => $c->filter(fn ($r) => $r->period_date?->year === $year))->values();
        $years = $runs->map(fn ($r) => $r->period_date?->year)->filter()->unique()->sortDesc()->values();

        return view('dashboard.payroll.flow.index', [
            'current'      => $current,
            'currentStep'  => $current ? $this->approvals->currentStep($current) : null,
            'waiting'      => $waiting,
            'inReview'     => $inReview,
            'history'      => $history,
            'years'        => $years,
            'year'         => $year,
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
        $existing = PayrollRun::all()->filter(fn ($r) => $r->period_date)->keyBy(fn ($r) => $r->period_date->format('Y-m'));

        $months = collect(range(-3, 2))->map(fn ($i) => now()->startOfMonth()->addMonths($i))
            ->map(fn ($m) => ['value' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'run' => $existing->get($m->format('Y-m'))]);

        return view('dashboard.payroll.flow.start', [
            'month'    => $month,
            'months'   => $months,
            'existing' => $existing->get($month->format('Y-m')),
            'preview'  => $this->insights->preview($month),
            'chain'    => $this->approvals->chain(),
            'approvals' => $this->approvals,
        ]);
    }

    public function store(Request $request, HrActions $hr)
    {
        $data = $request->validate([
            'period'           => 'required|date_format:Y-m',
            'alias'            => 'nullable|string|max:120',
            'include_overtime' => 'nullable|boolean',
        ]);

        $existing = PayrollRun::where('period', $data['period'])->latest('id')->first();
        if ($existing && ! $existing->isEditable()) {
            return redirect()->route('payroll.runs.show', $existing)
                ->with('info', 'This month\'s payroll is already ' . strtolower($existing->statusLabel()) . '.');
        }

        $month = Carbon::createFromFormat('Y-m', $data['period'])->startOfMonth();
        $run = $existing ?? PayrollRun::create([
            'period'     => $data['period'],
            'alias'      => ($data['alias'] ?? null) ?: $month->format('F Y') . ' payroll',
            'created_by' => $request->user()->id,
            'status'     => PayrollRun::DRAFT,
        ]);
        $run->update(['include_overtime' => $request->boolean('include_overtime', true)] + (filled($data['alias'] ?? null) ? ['alias' => $data['alias']] : []));

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
