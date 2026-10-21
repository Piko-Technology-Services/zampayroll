<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\Overtime\OvertimeService;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Overtime for HR and supervisors: approve (optionally correcting hours), reject,
 * cancel, record on someone's behalf, monthly report and the company's rates.
 */
class OvertimeDashboardController extends Controller
{
    public function __construct(private OvertimeService $ot)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'pending');
        $base = fn () => $this->ot->visibleQuery($user);

        $requests = $base()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))))
            ->when($request->query('month'), fn ($q, $m) => $q->whereBetween('date', [Carbon::parse($m . '-01')->startOfMonth(), Carbon::parse($m . '-01')->endOfMonth()]))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $month = now();
        $thisMonth = $base()->where('status', 'approved')->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);

        return view('dashboard.overtime.dashboard', [
            'requests' => $requests,
            'status' => $status,
            'counts' => [
                'pending' => $base()->where('status', 'pending')->count(),
                'approved' => $base()->where('status', 'approved')->count(),
                'rejected' => $base()->where('status', 'rejected')->count(),
                'cancelled' => $base()->where('status', 'cancelled')->count(),
            ],
            'kpi' => [
                'hours' => (float) (clone $thisMonth)->sum('hours'),
                'amount' => (float) (clone $thisMonth)->sum('amount'),
                'unpaid' => (float) $base()->where('status', 'approved')->whereNull('payroll_run_id')->sum('amount'),
            ],
            'types' => $this->ot->types(),
            'service' => $this->ot,
            'isHr' => TeamScope::isHr($user),
            'employees' => Employee::query()->where('employment_status', 'Active')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id', 'department', 'supervisor'])
                ->filter(fn ($e) => TeamScope::canSee($user, $e))->values(),
        ]);
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest)
    {
        $data = $request->validate(['comment' => 'nullable|string|max:500', 'hours' => 'nullable|numeric|min:0.01|max:24']);
        $ot = $this->ot->approve($overtimeRequest, $request->user(), $data['comment'] ?? null, isset($data['hours']) && $data['hours'] !== null ? (float) $data['hours'] : null);

        return back()->with('success', 'Overtime approved for ' . $ot->employee->full_name . ' (K ' . number_format((float) $ot->amount, 2) . '). It goes into the next payroll.');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest)
    {
        $request->validate(['comment' => 'nullable|string|max:500']);
        $this->ot->reject($overtimeRequest, $request->user(), $request->input('comment'));

        return back()->with('success', 'Overtime rejected.');
    }

    public function cancel(Request $request, OvertimeRequest $overtimeRequest)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $inPayroll = $overtimeRequest->payroll_run_id;
        $this->ot->cancel($overtimeRequest, $request->user(), $request->input('reason'));

        return back()->with('success', 'Overtime cancelled.' . ($inPayroll ? ' It was taken out of the payroll being prepared — recalculate that payroll to update payslips.' : ''));
    }

    public function record(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer', 'date' => 'required|date', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:720', 'type' => 'required|string|max:40', 'comment' => 'nullable|string|max:1000', 'approve_now' => 'nullable|boolean',
        ]);
        $employee = Employee::query()->findOrFail($data['employee_id']);
        abort_unless(TeamScope::canSee($request->user(), $employee), 404);

        $ot = $this->ot->apply($employee, $data, $request->user(), notify: empty($data['approve_now']));
        if (! empty($data['approve_now'])) {
            try {
                $ot = $this->ot->approve($ot, $request->user(), 'Entered by ' . $request->user()->name);
            } catch (ValidationException $e) {
                return back()->with('success', 'Overtime saved for ' . $employee->full_name . ', waiting for approval: ' . collect($e->errors())->flatten()->first());
            }
        }

        return back()->with('success', 'Overtime saved for ' . $employee->full_name . ': ' . $this->ot->fmt($ot->hours) . ' hours, K ' . number_format((float) $ot->amount, 2)
            . ($ot->status === 'approved' ? ', approved.' : ', waiting for approval.'));
    }

    public function check(Request $request)
    {
        $employee = Employee::query()->findOrFail($request->query('employee_id'));
        abort_unless(TeamScope::canSee($request->user(), $employee), 404);
        try {
            $c = $this->ot->check($employee, $request->query() + ['comment' => 'x'], null, true);

            return response()->json(['ok' => true, 'hours' => $c['hours'], 'amount' => $c['amount'], 'hourly_rate' => $c['hourly_rate'], 'overnight' => $c['overnight'], 'warnings' => $c['warnings']]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    /** Monthly summary per person (screen or CSV). */
    public function report(Request $request)
    {
        $month = $request->query('month') ? Carbon::parse($request->query('month') . '-01') : now()->startOfMonth();
        $rows = $this->ot->visibleQuery($request->user())->where('status', 'approved')
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')->get()->groupBy('employee_id')
            ->map(function ($items) {
                $e = $items->first()->employee;

                return [
                    'employee' => $e,
                    'claims' => $items->count(),
                    'hours' => (float) $items->sum('hours'),
                    'amount' => (float) $items->sum('amount'),
                    'by_type' => $items->groupBy('type')->map(fn ($g) => (float) $g->sum('hours'))->all(),
                    'in_payroll' => $items->whereNotNull('payroll_run_id')->count(),
                    'items' => $items,
                ];
            })->sortBy(fn ($r) => $r['employee']?->first_name)->values();

        if ($request->query('format') === 'csv') {
            $types = $this->ot->types();

            return response()->streamDownload(function () use ($rows, $types) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_merge(['Employee number', 'Name', 'Department', 'Claims'], array_map(fn ($t) => $t['label'] . ' (hours)', $types), ['Total hours', 'Amount (K)', 'Claims already in a payroll']));
                foreach ($rows as $r) {
                    fputcsv($out, array_merge([$r['employee']?->employee_id, $r['employee']?->full_name, $r['employee']?->department, $r['claims']],
                        array_map(fn ($k) => $r['by_type'][$k] ?? 0, array_keys($types)), [$r['hours'], number_format($r['amount'], 2, '.', ''), $r['in_payroll']]));
                }
                fclose($out);
            }, 'overtime-' . $month->format('Y-m') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('dashboard.overtime.report', ['rows' => $rows, 'month' => $month, 'types' => $this->ot->types()]);
    }

    public function settings(Request $request)
    {
        abort_unless(TeamScope::isHr($request->user()), 403, 'Only HR and administrators can change overtime rates.');

        return view('dashboard.overtime.settings', ['s' => $this->ot->settings()]);
    }

    public function saveSettings(Request $request)
    {
        abort_unless(TeamScope::isHr($request->user()), 403);
        $data = $request->validate([
            'rates' => 'required|array|min:1|max:8',
            'rates.*.label' => 'required|string|max:60',
            'rates.*.hint' => 'nullable|string|max:120',
            'rates.*.multiplier' => 'required|numeric|min:1|max:5',
            'rates.*.key' => 'nullable|string|max:40',
            'base' => 'required|in:basic,gross',
            'hours_per_day' => 'required|numeric|min:1|max:24',
            'max_hours_per_day' => 'required|numeric|min:1|max:24',
            'days_back' => 'required|integer|min:1|max:365',
        ]);

        // Keep existing keys so past claims keep their names; new rows get a key from the label.
        $rates = [];
        foreach ($data['rates'] as $r) {
            $key = $r['key'] ?: \Illuminate\Support\Str::slug($r['label'], '_');
            $key = \Illuminate\Support\Str::limit($key ?: 'rate', 36, '');
            while (isset($rates[$key])) {
                $key .= '_2';
            }
            $rates[$key] = ['label' => $r['label'], 'hint' => $r['hint'] ?? '', 'multiplier' => (float) $r['multiplier']];
        }

        $this->ot->saveSettings($request->user()->company, [
            'rates' => $rates, 'base' => $data['base'], 'hours_per_day' => (float) $data['hours_per_day'],
            'max_hours_per_day' => (float) $data['max_hours_per_day'], 'require_reason' => $request->boolean('require_reason'), 'days_back' => (int) $data['days_back'],
        ], $request->user());

        return back()->with('success', 'Overtime rates saved. They apply to new claims; claims already made keep the rate they were made with.');
    }

    /** Kept for old links: the monthly CSV now lives in report(). */
    public function exportApprovedOvertime(Request $request)
    {
        $request->validate(['month' => 'required|integer|between:1,12', 'year' => 'required|integer|min:2000|max:2100']);

        return redirect()->route('overtime.report', ['month' => sprintf('%04d-%02d', $request->query('year'), $request->query('month')), 'format' => 'csv']);
    }
}
