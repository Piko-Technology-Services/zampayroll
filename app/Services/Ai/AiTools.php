<?php

namespace App\Services\Ai;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\EmployeePortalService;
use App\Services\HrActions;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Everything AI mode can look up or do.
 *
 * Read tools run immediately (all queries are tenant-scoped by the
 * BelongsToCompany global scope). Write tools only PROPOSE an action:
 * the user sees a confirmation card and nothing changes until they click Confirm.
 */
class AiTools
{
    public const STATUTORY_CODES = ['D00' => 'PAYE (ZRA)', 'D01' => 'NAPSA', 'D02' => 'NHIMA'];

    public function __construct(private User $user)
    {
    }

    /* ================================================================== */
    /*  Definitions (Anthropic tool-use schema)                            */
    /* ================================================================== */

    public static function definitions(): array
    {
        $kind = ['type' => 'string', 'enum' => ['leave', 'overtime', 'loan']];

        return [
            [
                'name' => 'company_overview',
                'description' => 'Headcount and workforce snapshot: total/active employees, departments, branches, new hires this month, contracts ending soon, pending approvals.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name' => 'search_employees',
                'description' => 'Find employees by name, employee number, NRC, email, position or department. Returns ids needed for other tools.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Free text; leave empty to list everyone'],
                    'department' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['Active', 'Exited', 'Suspended', 'any']],
                    'limit' => ['type' => 'integer', 'default' => 25],
                ]],
            ],
            [
                'name' => 'who_is_on_leave',
                'description' => 'Employees on approved leave on a date (default today), plus pending leave that covers that date.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default today'],
                ]],
            ],
            [
                'name' => 'list_requests',
                'description' => 'Leave, overtime or loan requests with their ids, filtered by status and optionally employee.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'kind' => $kind,
                    'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'all'], 'default' => 'pending'],
                    'employee_query' => ['type' => 'string', 'description' => 'Optional name/number filter'],
                    'limit' => ['type' => 'integer', 'default' => 25],
                ], 'required' => ['kind']],
            ],
            [
                'name' => 'payroll_summary',
                'description' => 'Totals for a payroll run: gross, deductions, net pay, PAYE, NAPSA, NHIMA, headcount, and change vs the previous run. period may be "latest", "previous" or YYYY-MM.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'period' => ['type' => 'string', 'default' => 'latest'],
                ]],
            ],
            [
                'name' => 'list_payroll_runs',
                'description' => 'Recent payroll runs with period, status and totals.',
                'input_schema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'default' => 6]]],
            ],
            [
                'name' => 'attendance_on',
                'description' => 'Attendance counts by status (present, absent, sick, leave...) for a date (default today) and who was absent.',
                'input_schema' => ['type' => 'object', 'properties' => ['date' => ['type' => 'string']]],
            ],
            [
                'name' => 'loans_overview',
                'description' => 'Active staff loans, outstanding balances and pending loan requests.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],

            // ---------------- actions (need user confirmation) ----------------
            [
                'name' => 'propose_add_employees',
                'description' => 'Prepare new employee records from text, a pasted list or an attached file. Extract every person you can; map NAPSA number to "ssn". net_salary = monthly take-home pay the payroll grosses up from; salary = basic/gross if that is what was given. Dates as YYYY-MM-DD. The user reviews a preview and confirms.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'employees' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'first_name' => ['type' => 'string'], 'middle_name' => ['type' => 'string'], 'last_name' => ['type' => 'string'],
                        'position' => ['type' => 'string'], 'department' => ['type' => 'string'], 'branch' => ['type' => 'string'],
                        'company_email' => ['type' => 'string'], 'personal_email' => ['type' => 'string'], 'primary_phone' => ['type' => 'string'],
                        'nrc_no' => ['type' => 'string'], 'tpin' => ['type' => 'string'], 'ssn' => ['type' => 'string', 'description' => 'NAPSA number'],
                        'nhima_no' => ['type' => 'string'], 'bank_name' => ['type' => 'string'], 'bank_account_no' => ['type' => 'string'],
                        'net_salary' => ['type' => 'number'], 'salary' => ['type' => 'number'],
                        'date_of_birth' => ['type' => 'string'], 'gender' => ['type' => 'string'], 'contract_start' => ['type' => 'string'],
                        'leave_days_entitled' => ['type' => 'number'],
                    ], 'required' => ['first_name', 'last_name']]],
                ], 'required' => ['employees']],
            ],
            [
                'name' => 'propose_decide_requests',
                'description' => 'Approve or reject leave/overtime/loan requests by id (get ids from list_requests first).',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'kind' => $kind,
                    'request_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'decision' => ['type' => 'string', 'enum' => ['approve', 'reject']],
                    'comment' => ['type' => 'string'],
                ], 'required' => ['kind', 'request_ids', 'decision']],
            ],
            [
                'name' => 'propose_prepare_payroll',
                'description' => 'Create (or refresh) the payroll run for a month and calculate pay, PAYE, NAPSA, NHIMA and loan deductions for every active employee. Does not finalise.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'period' => ['type' => 'string', 'description' => 'YYYY-MM'],
                    'alias' => ['type' => 'string'],
                ], 'required' => ['period']],
            ],
            [
                'name' => 'propose_create_portals',
                'description' => 'Create self-service portal accounts (emailed login details) for employees by id, or for everyone eligible.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'employee_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'all_eligible' => ['type' => 'boolean'],
                ]],
            ],
        ];
    }

    /* ================================================================== */
    /*  Dispatcher                                                          */
    /* ================================================================== */

    /**
     * @return array{result: array, card: ?array}
     */
    /** Which role permission each tool needs (see Settings → Team & roles). */
    private function permissionFor(string $name, array $input): ?string
    {
        $kind = $input['kind'] ?? 'leave';

        return match ($name) {
            'company_overview', 'search_employees' => 'employees.view',
            'who_is_on_leave' => 'leave.view',
            'list_requests' => ['leave' => 'leave.view', 'overtime' => 'overtime.approve', 'loan' => 'loans.view'][$kind] ?? 'leave.view',
            'payroll_summary', 'list_payroll_runs' => 'payroll.view',
            'attendance_on' => 'attendance.view',
            'loans_overview' => 'loans.view',
            'propose_add_employees' => 'employees.manage',
            'propose_decide_requests' => ['leave' => 'leave.approve', 'overtime' => 'overtime.approve', 'loan' => 'loans.manage'][$kind] ?? 'leave.approve',
            'propose_prepare_payroll' => 'payroll.run',
            'propose_create_portals' => 'portal.manage',
            default => null,
        };
    }

    public function run(string $name, array $input): array
    {
        $method = Str::camel($name);

        $needed = $this->permissionFor($name, $input);
        if ($needed && ! $this->user->hasPermission($needed)) {
            return ['result' => ['error' => 'Not allowed: the user\'s role does not include "' . \App\Support\Permissions::label($needed) . '". Tell them to ask their administrator.'], 'card' => null];
        }

        if (! method_exists($this, $method)) {
            return ['result' => ['error' => "Unknown tool {$name}"], 'card' => null];
        }

        try {
            return $this->{$method}($input);
        } catch (\Throwable $e) {
            report($e);

            return ['result' => ['error' => $e->getMessage()], 'card' => null];
        }
    }

    /* ================================================================== */
    /*  Read tools                                                          */
    /* ================================================================== */

    protected function companyOverview(array $in): array
    {
        $active = Employee::where('employment_status', 'Active');

        $data = [
            'company' => Tenancy::company()?->name,
            'total_employees' => Employee::count(),
            'active_employees' => (clone $active)->count(),
            'departments' => Employee::whereNotNull('department')->distinct()->count('department'),
            'branches' => Employee::whereNotNull('branch')->distinct()->count('branch'),
            'new_this_month' => Employee::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
            'contracts_ending_30_days' => Employee::whereNotNull('contract_end')->whereBetween('contract_end', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'with_portal_access' => Employee::has('user')->count(),
            'pending' => $this->pendingCounts(),
            'by_department' => Employee::where('employment_status', 'Active')->selectRaw("COALESCE(department,'Unassigned') d, COUNT(*) c")->groupBy('d')->orderByDesc('c')->pluck('c', 'd'),
        ];

        return ['result' => $data, 'card' => ['type' => 'stats', 'title' => 'Workforce today', 'items' => [
            ['label' => 'Active employees', 'value' => $data['active_employees'], 'link' => route('employees.index')],
            ['label' => 'Departments', 'value' => $data['departments']],
            ['label' => 'New this month', 'value' => $data['new_this_month']],
            ['label' => 'Awaiting approval', 'value' => array_sum($data['pending']), 'link' => route('leave.dashboard')],
        ]]];
    }

    protected function searchEmployees(array $in): array
    {
        $q = trim((string) ($in['query'] ?? ''));
        $limit = min(100, max(1, (int) ($in['limit'] ?? 25)));
        $status = $in['status'] ?? 'any';

        $rows = Employee::query()
            ->when($status !== 'any' && $status, fn ($w) => $w->where('employment_status', $status))
            ->when(! empty($in['department']), fn ($w) => $w->where('department', 'like', '%' . $in['department'] . '%'))
            ->when($q !== '', function ($w) use ($q) {
                foreach (preg_split('/\s+/', $q) as $term) {
                    $w->where(fn ($x) => $x->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('employee_id', 'like', "%{$term}%")->orWhere('nrc_no', 'like', "%{$term}%")
                        ->orWhere('company_email', 'like', "%{$term}%")->orWhere('position', 'like', "%{$term}%")
                        ->orWhere('department', 'like', "%{$term}%"));
                }
            })
            ->orderBy('first_name')->limit($limit)->get();

        $list = $rows->map(fn (Employee $e) => [
            'id' => $e->id, 'number' => $e->employee_id, 'name' => trim($e->first_name . ' ' . $e->last_name),
            'position' => $e->position, 'department' => $e->department, 'status' => $e->employment_status,
            'net_salary' => (float) $e->net_salary, 'leave_balance' => $e->leave_days_balance,
            'email' => $e->company_email ?: $e->personal_email,
        ]);

        return ['result' => ['count' => $list->count(), 'employees' => $list], 'card' => $list->isEmpty() ? null : [
            'type' => 'table', 'title' => $list->count() . ' employee' . ($list->count() === 1 ? '' : 's'),
            'columns' => ['Name', 'Position', 'Department', 'Status'],
            'rows' => $list->map(fn ($e) => [
                ['text' => $e['name'], 'link' => route('employees.show', $e['id'])], $e['position'] ?? '—', $e['department'] ?? '—', $e['status'],
            ])->all(),
        ]];
    }

    protected function whoIsOnLeave(array $in): array
    {
        $date = $this->date($in['date'] ?? null);

        $on = LeaveRequest::with('employee')->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get();
        $pending = LeaveRequest::with('employee')->where('status', 'pending')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get();

        $fmt = fn ($r) => [
            'request_id' => $r->id, 'employee_id' => $r->employee_id, 'name' => $r->employee?->full_name,
            'type' => LeaveRequest::TYPES[$r->leave_type] ?? $r->leave_type,
            'from' => $r->start_date->toDateString(), 'to' => $r->end_date->toDateString(),
            'back' => optional($r->return_date)->toDateString(),
        ];

        $label = $date->isToday() ? 'today' : $date->format('D d M Y');

        return ['result' => ['date' => $date->toDateString(), 'on_leave' => $on->map($fmt), 'pending_covering_date' => $pending->map($fmt)],
            'card' => $on->isEmpty() ? null : [
                'type' => 'table', 'title' => "On leave {$label}",
                'columns' => ['Employee', 'Leave', 'Until', 'Back'],
                'rows' => $on->map(fn ($r) => [
                    ['text' => $r->employee?->full_name, 'link' => route('employees.show', $r->employee_id)],
                    LeaveRequest::TYPES[$r->leave_type] ?? $r->leave_type, $r->end_date->format('d M'), optional($r->return_date)->format('D d M'),
                ])->all(),
            ]];
    }

    protected function listRequests(array $in): array
    {
        $kind = $in['kind'] ?? 'leave';
        $status = $in['status'] ?? 'pending';
        $limit = min(100, (int) ($in['limit'] ?? 25));
        $model = ['leave' => LeaveRequest::class, 'overtime' => OvertimeRequest::class, 'loan' => LoanRequest::class][$kind];

        $q = $model::with('employee')->when($status !== 'all', fn ($w) => $kind === 'leave' && $status === 'pending'
            ? $w->whereIn('status', LeaveRequest::OPEN) : $w->where('status', $status))->latest();

        if (! empty($in['employee_query'])) {
            $ids = $this->matchEmployeeIds($in['employee_query']);
            $q->whereIn('employee_id', $ids ?: [0]);
        }

        $items = $q->limit($limit)->get()->map(fn ($r) => $this->describeRequest($kind, $r));

        $dash = ['leave' => 'leave.dashboard', 'overtime' => 'overtime.dashboard', 'loan' => 'loan.dashboard'][$kind];

        return ['result' => ['kind' => $kind, 'status' => $status, 'count' => $items->count(), 'requests' => $items],
            'card' => $items->isEmpty() ? null : [
                'type' => 'table', 'title' => ucfirst($status) . " {$kind} requests", 'link' => route($dash),
                'columns' => ['#', 'Employee', 'Details', 'Status'],
                'rows' => $items->map(fn ($r) => [$r['id'], $r['employee'], $r['summary'], $r['status']])->all(),
            ]];
    }

    protected function payrollSummary(array $in): array
    {
        $run = $this->resolveRun($in['period'] ?? 'latest');

        if (! $run) {
            return ['result' => ['message' => 'No payroll run found for that period.', 'available_runs' => PayrollRun::latest()->limit(6)->pluck('period')], 'card' => null];
        }

        $data = $this->runFigures($run);
        $prev = PayrollRun::where('id', '<', $run->id)->whereIn('status', ['Approved', 'Processed', 'Paid'])->orderByDesc('id')->first();

        if ($prev) {
            $p = $this->runFigures($prev);
            $data['previous'] = ['period' => $prev->period, 'net_pay' => $p['net_pay'], 'gross' => $p['gross']];
            $data['net_change_pct'] = $p['net_pay'] > 0 ? round(($data['net_pay'] - $p['net_pay']) / $p['net_pay'] * 100, 1) : null;
        }

        return ['result' => $data, 'card' => [
            'type' => 'stats', 'title' => ($run->alias ?: $this->periodLabel($run->period)) . ' — ' . $run->status,
            'link' => route('payroll.runs.show', $run),
            'items' => [
                ['label' => 'Gross pay', 'value' => $this->k($data['gross'])],
                ['label' => 'Net pay', 'value' => $this->k($data['net_pay']), 'delta' => $data['net_change_pct'] ?? null],
                ['label' => 'PAYE (ZRA)', 'value' => $this->k($data['statutory']['PAYE (ZRA)'] ?? 0)],
                ['label' => 'NAPSA', 'value' => $this->k($data['statutory']['NAPSA'] ?? 0)],
                ['label' => 'NHIMA', 'value' => $this->k($data['statutory']['NHIMA'] ?? 0)],
                ['label' => 'Employees paid', 'value' => $data['employees']],
            ],
        ]];
    }

    protected function listPayrollRuns(array $in): array
    {
        $runs = PayrollRun::withCount('payrolls')->latest()->limit(min(24, (int) ($in['limit'] ?? 6)))->get();

        $list = $runs->map(fn ($r) => [
            'id' => $r->id, 'period' => $r->period, 'name' => $r->alias, 'status' => $r->status,
            'employees' => $r->payrolls_count, 'net_pay' => (float) ($r->net_pay ?: $r->payrolls()->sum('net_pay')),
        ]);

        return ['result' => ['runs' => $list], 'card' => $list->isEmpty() ? null : [
            'type' => 'table', 'title' => 'Payroll runs', 'link' => route('payroll.runs.index'),
            'columns' => ['Period', 'Status', 'Staff', 'Net pay'],
            'rows' => $list->map(fn ($r) => [['text' => $r['name'] ?: $this->periodLabel($r['period']), 'link' => route('payroll.runs.show', $r['id'])], $r['status'], $r['employees'], $this->k($r['net_pay'])])->all(),
        ]];
    }

    protected function attendanceOn(array $in): array
    {
        $date = $this->date($in['date'] ?? null);
        $rows = Attendance::with('employee')->whereDate('date', $date)->get();
        $counts = $rows->groupBy('status')->map->count();
        $absent = $rows->whereIn('status', ['absent', 'sick'])->map(fn ($a) => ['name' => $a->employee?->full_name, 'status' => $a->status, 'note' => $a->note])->values();

        return ['result' => [
            'date' => $date->toDateString(), 'marked' => $rows->count(),
            'active_employees' => Employee::where('employment_status', 'Active')->count(),
            'by_status' => $counts, 'absent_or_sick' => $absent,
            'note' => $rows->isEmpty() ? 'Attendance has not been marked for this date.' : null,
        ], 'card' => $rows->isEmpty() ? null : ['type' => 'stats', 'title' => 'Attendance ' . $date->format('D d M'), 'link' => route('attendance.day', $date->toDateString()),
            'items' => collect(Attendance::STATUSES)->map(fn ($label, $k) => ['label' => $label, 'value' => $counts[$k] ?? 0])->filter(fn ($i) => $i['value'] > 0)->values()->all()]];
    }

    protected function loansOverview(array $in): array
    {
        $active = Loan::with('employee')->where('status', 'active')->get();

        return ['result' => [
            'active_loans' => $active->count(),
            'outstanding_total' => (float) $active->sum('balance'),
            'pending_requests' => LoanRequest::where('status', 'pending')->count(),
            'largest_balances' => $active->sortByDesc('balance')->take(5)->map(fn ($l) => ['name' => $l->employee?->full_name, 'balance' => (float) $l->balance])->values(),
        ], 'card' => ['type' => 'stats', 'title' => 'Staff loans', 'link' => route('loan.ledger.index'), 'items' => [
            ['label' => 'Active loans', 'value' => $active->count()],
            ['label' => 'Outstanding', 'value' => $this->k($active->sum('balance'))],
            ['label' => 'Requests waiting', 'value' => LoanRequest::where('status', 'pending')->count()],
        ]]];
    }

    /* ================================================================== */
    /*  Proposals (write actions — need confirmation)                       */
    /* ================================================================== */

    protected function proposeAddEmployees(array $in): array
    {
        $actions = app(HrActions::class);
        $rows = [];
        $previewRows = [];
        $dupes = 0;

        foreach (array_slice($in['employees'] ?? [], 0, 500) as $raw) {
            [$clean, $warnings] = $actions->normaliseEmployee((array) $raw);
            if (empty($clean['first_name']) || empty($clean['last_name'])) {
                continue;
            }
            $dup = $actions->findDuplicate($clean);
            if ($dup) {
                $dupes++;
                $warnings[] = "already exists ({$dup->employee_id}) — will be skipped";
            }
            $rows[] = ['data' => $clean, 'skip' => (bool) $dup];
            $previewRows[] = [
                trim($clean['first_name'] . ' ' . $clean['last_name']),
                $clean['position'] ?? '—',
                isset($clean['net_salary']) ? $this->k($clean['net_salary']) : (isset($clean['salary']) ? $this->k($clean['salary']) . ' gross' : '—'),
                $warnings ? implode(', ', $warnings) : 'Ready',
            ];
        }

        $new = count($rows) - $dupes;

        if ($new === 0) {
            return ['result' => ['error' => 'No new employees could be prepared (missing names or all already exist).', 'duplicates' => $dupes], 'card' => null];
        }

        return $this->proposal('add_employees', ['rows' => $rows],
            "Add {$new} employee" . ($new === 1 ? '' : 's'),
            $dupes ? "{$dupes} already on file will be skipped. Missing details can be completed later from each employee's file." : 'Missing details can be completed later from each employee\'s file.',
            ['columns' => ['Name', 'Position', 'Net pay', 'Check'], 'rows' => $previewRows],
            ['new' => $new, 'skipped_duplicates' => $dupes]
        );
    }

    protected function proposeDecideRequests(array $in): array
    {
        $kind = $in['kind'];
        $decision = $in['decision'] === 'reject' ? 'rejected' : 'approved';
        $model = ['leave' => LeaveRequest::class, 'overtime' => OvertimeRequest::class, 'loan' => LoanRequest::class][$kind];

        $items = $model::with('employee')->whereIn('id', $in['request_ids'] ?? [])->where('status', 'pending')->get();

        if ($items->isEmpty()) {
            return ['result' => ['error' => 'None of those requests are pending (they may already be decided).'], 'card' => null];
        }

        $verb = $decision === 'approved' ? 'Approve' : 'Reject';

        return $this->proposal('decide_requests', [
            'kind' => $kind, 'ids' => $items->pluck('id')->all(), 'status' => $decision, 'comment' => $in['comment'] ?? null,
        ],
            "{$verb} {$items->count()} {$kind} request" . ($items->count() === 1 ? '' : 's'),
            $kind === 'leave' && $decision === 'approved' ? 'Leave balances and the leave sheet update automatically, and each employee is emailed.' : 'Each employee is emailed the decision.',
            ['columns' => ['Employee', 'Details'], 'rows' => $items->map(fn ($r) => [$r->employee?->full_name, $this->describeRequest($kind, $r)['summary']])->all()],
            ['count' => $items->count()]
        );
    }

    protected function proposePreparePayroll(array $in): array
    {
        try {
            $period = Carbon::createFromFormat('Y-m', $in['period'])->format('Y-m');
        } catch (\Throwable $e) {
            return ['result' => ['error' => 'period must be YYYY-MM'], 'card' => null];
        }

        $locked = PayrollRun::where('period', $period)->latest('id')->first();
        if ($locked && ! $locked->isEditable()) {
            return ['result' => ['error' => $locked->isInReview()
                ? "The {$period} payroll is waiting for approval. Recall it from the payroll page to change it."
                : "The {$period} payroll is already approved and locked."], 'card' => null];
        }

        $existing = PayrollRun::where('period', $period)->latest()->first();
        $active = Employee::where('employment_status', 'Active')->count();
        $noSalary = Employee::where('employment_status', 'Active')->where(fn ($q) => $q->whereNull('net_salary')->orWhere('net_salary', 0))->count();
        $pendingOt = OvertimeRequest::where('status', 'pending')->count();

        $notes = [];
        if ($noSalary) {
            $notes[] = "{$noSalary} active employee(s) have no salary set and will show K0.";
        }
        if ($pendingOt) {
            $notes[] = "{$pendingOt} overtime request(s) are still pending.";
        }

        return $this->proposal('prepare_payroll', ['period' => $period, 'alias' => $in['alias'] ?? null],
            ($existing ? 'Recalculate ' : 'Prepare ') . $this->periodLabel($period) . ' payroll',
            "Calculates pay, PAYE, NAPSA, NHIMA and loan repayments for {$active} active employees. Nothing is paid or finalised — you review the run first." . ($notes ? ' ' . implode(' ', $notes) : ''),
            null,
            ['employees' => $active, 'existing_run' => (bool) $existing]
        );
    }

    protected function proposeCreatePortals(array $in): array
    {
        $portal = app(EmployeePortalService::class);

        $q = Employee::query()->doesntHave('user');
        if (empty($in['all_eligible'])) {
            $q->whereIn('id', $in['employee_ids'] ?? [0]);
        }

        $ready = [];
        $problems = [];
        foreach ($q->get() as $e) {
            ($p = $portal->eligibilityProblem($e)) ? $problems[] = "{$e->full_name}: {$p}" : $ready[] = $e;
        }

        if (! $ready) {
            return ['result' => ['error' => 'Nobody is eligible', 'problems' => $problems], 'card' => null];
        }

        return $this->proposal('create_portals', ['ids' => collect($ready)->pluck('id')->all()],
            'Create ' . count($ready) . ' self-service portal' . (count($ready) === 1 ? '' : 's'),
            'Each employee gets an email with a temporary password and instructions.' . ($problems ? ' Skipped: ' . implode('; ', array_slice($problems, 0, 3)) : ''),
            ['columns' => ['Employee', 'Email'], 'rows' => collect($ready)->map(fn ($e) => [$e->full_name, $e->portal_email])->all()],
            ['count' => count($ready)]
        );
    }

    /* ================================================================== */
    /*  Executing confirmed actions                                         */
    /* ================================================================== */

    /**
     * @return array{message:string, link?:string, link_label?:string}
     */
    public function execute(array $action): array
    {
        $hr = app(HrActions::class);
        $p = $action['payload'];

        $needed = $this->permissionFor('propose_' . $action['type'], $p + ['kind' => $p['kind'] ?? 'leave']);
        if ($needed && ! $this->user->hasPermission($needed)) {
            throw new \RuntimeException('Your role doesn\'t allow this action.');
        }

        switch ($action['type']) {
            case 'add_employees':
                $created = [];
                foreach ($p['rows'] as $row) {
                    if ($row['skip'] || $hr->findDuplicate($row['data'])) {
                        continue;
                    }
                    $created[] = $hr->createEmployee($row['data']);
                }

                return ['message' => '✅ Added ' . count($created) . ' employee' . (count($created) === 1 ? '' : 's') . '. Want me to create their self-service portals too?',
                    'link' => route('employees.index'), 'link_label' => 'Open employees'];

            case 'decide_requests':
                $model = ['leave' => LeaveRequest::class, 'overtime' => OvertimeRequest::class, 'loan' => LoanRequest::class][$p['kind']];
                $done = 0;
                $problems = [];
                $open = $p['kind'] === 'leave' ? LeaveRequest::OPEN : ['pending'];
                foreach ($model::whereIn('id', $p['ids'])->whereIn('status', $open)->get() as $r) {
                    try {
                        match ($p['kind']) {
                            'leave' => $hr->decideLeave($r, $p['status'], $p['comment'], $this->user),
                            'overtime' => $hr->decideOvertime($r, $p['status'], $p['comment'], $this->user),
                            'loan' => $hr->decideLoan($r, $p['status'], $p['comment'], $this->user),
                        };
                        $done++;
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $problems[] = ($r->employee?->full_name ?? "#{$r->id}") . ': ' . collect($e->errors())->flatten()->first();
                    }
                }
                if ($problems) {
                    return ['message' => "✅ {$done} done. Not done: " . implode('; ', $problems), 'link' => route(['leave' => 'leave.dashboard', 'overtime' => 'overtime.dashboard', 'loan' => 'loan.dashboard'][$p['kind']]), 'link_label' => 'View requests'];
                }
                $dash = ['leave' => 'leave.dashboard', 'overtime' => 'overtime.dashboard', 'loan' => 'loan.dashboard'][$p['kind']];

                return ['message' => "✅ {$done} {$p['kind']} request" . ($done === 1 ? '' : 's') . ' ' . $p['status'] . '. The employees have been notified.'
                    . ($p['kind'] === 'loan' && $p['status'] === 'approved' ? ' Approved loans are on the ledger as "not paid out yet" — record the payout on the Loans page and repayments start from the next payroll.' : ''),
                    'link' => route($dash), 'link_label' => 'View requests'];

            case 'prepare_payroll':
                $run = $hr->preparePayroll($p['period'], $p['alias'], $this->user);
                $f = $this->runFigures($run);

                return ['message' => "✅ {$this->periodLabel($run->period)} payroll is ready for review: {$f['employees']} employees, net pay " . $this->k($f['net_pay'])
                    . ', PAYE ' . $this->k($f['statutory']['PAYE (ZRA)'] ?? 0) . ', NAPSA ' . $this->k($f['statutory']['NAPSA'] ?? 0) . '. Check the highlights, then submit it for approval.',
                    'link' => route('payroll.runs.show', $run), 'link_label' => 'Review payroll'];

            case 'create_portals':
                $portal = app(EmployeePortalService::class);
                $n = 0;
                foreach (Employee::whereIn('id', $p['ids'])->doesntHave('user')->get() as $e) {
                    if (! $portal->eligibilityProblem($e)) {
                        $portal->grant($e, $this->user);
                        $n++;
                    }
                }

                return ['message' => "✅ {$n} portal" . ($n === 1 ? '' : 's') . ' created — login details have been emailed.', 'link' => route('employees.index'), 'link_label' => 'Open employees'];
        }

        throw new \RuntimeException('Unknown action');
    }

    /* ================================================================== */
    /*  Helpers                                                             */
    /* ================================================================== */

    private function proposal(string $type, array $payload, string $title, string $note, ?array $table, array $resultExtra): array
    {
        $token = Str::random(24);

        Cache::put($this->actionKey($token), [
            'type' => $type, 'payload' => $payload, 'title' => $title,
            'user_id' => $this->user->id, 'company_id' => $this->user->company_id,
        ], now()->addMinutes(config('ai.action_ttl_minutes', 15)));

        return [
            'result' => array_merge(['status' => 'awaiting_user_confirmation', 'summary' => $title,
                'instruction' => 'A confirmation card is shown to the user. Do not say it is done; ask them to review and press Confirm.'], $resultExtra),
            'card' => ['type' => 'action', 'token' => $token, 'title' => $title, 'note' => $note, 'table' => $table],
        ];
    }

    public function actionKey(string $token): string
    {
        return "ai_action:{$this->user->id}:{$token}";
    }

    public function pendingCounts(): array
    {
        return [
            'leave' => LeaveRequest::whereIn('status', LeaveRequest::OPEN)->count(),
            'overtime' => OvertimeRequest::where('status', 'pending')->count(),
            'loans' => LoanRequest::where('status', 'pending')->count(),
        ];
    }

    public function matchEmployeeIds(string $query): array
    {
        $q = trim($query);

        if (preg_match('/^\d+$/', $q)) {
            return [(int) $q];
        }

        return Employee::where(function ($w) use ($q) {
            foreach (preg_split('/\s+/', $q) as $t) {
                $w->where(fn ($x) => $x->where('first_name', 'like', "%{$t}%")->orWhere('last_name', 'like', "%{$t}%")->orWhere('employee_id', 'like', "%{$t}%"));
            }
        })->pluck('id')->all();
    }

    public function describeRequest(string $kind, $r): array
    {
        $summary = match ($kind) {
            'leave' => (LeaveRequest::TYPES[$r->leave_type] ?? 'Leave') . ', ' . $r->start_date->format('d M') . '–' . $r->end_date->format('d M Y') . ' (' . rtrim(rtrim((string) $r->days, '0'), '.') . ' days)',
            'overtime' => $r->date->format('d M Y') . ', ' . rtrim(rtrim((string) $r->hours, '0'), '.') . ' hrs, ' . $this->k($r->amount),
            'loan' => $this->k($r->amount) . ', ' . (LoanRequest::PAYMENT_PLANS[$r->payment_plan] ?? $r->payment_plan),
        };

        return ['id' => $r->id, 'employee_id' => $r->employee_id, 'employee' => $r->employee?->full_name, 'summary' => $summary,
            'status' => $r->status, 'submitted' => $r->created_at?->toDateString(), 'reason' => $r->reason ?? $r->comment ?? null];
    }

    public function resolveRun(string $period): ?PayrollRun
    {
        $period = strtolower(trim($period));

        if (in_array($period, ['', 'latest', 'last', 'current'], true)) {
            return PayrollRun::latest('id')->first();
        }
        if ($period === 'previous') {
            return PayrollRun::latest('id')->skip(1)->first();
        }

        try {
            $p = Carbon::parse(strlen($period) === 7 ? $period . '-01' : $period)->format('Y-m');
        } catch (\Throwable $e) {
            return null;
        }

        return PayrollRun::where('period', $p)->latest('id')->first()
            ?? PayrollRun::get()->first(fn ($r) => optional($r->period_date)->format('Y-m') === $p);
    }

    public function runFigures(PayrollRun $run): array
    {
        $payrolls = $run->payrolls();
        $statutory = PayrollItem::whereIn('payroll_id', $run->payrolls()->pluck('id'))
            ->whereIn('code', array_keys(self::STATUTORY_CODES))
            ->selectRaw('code, SUM(amount) total')->groupBy('code')->pluck('total', 'code');

        return [
            'run_id' => $run->id, 'period' => $run->period, 'name' => $run->alias, 'status' => $run->status,
            'employees' => (clone $payrolls)->count(),
            'gross' => (float) (clone $payrolls)->sum('total_income'),
            'deductions' => (float) (clone $payrolls)->sum('total_deductions'),
            'net_pay' => (float) (clone $payrolls)->sum('net_pay'),
            'statutory' => collect(self::STATUTORY_CODES)->mapWithKeys(fn ($label, $code) => [$label => (float) ($statutory[$code] ?? 0)])->all(),
        ];
    }

    public function periodLabel(?string $period): string
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $period)->format('F Y');
        } catch (\Throwable $e) {
            return (string) $period;
        }
    }

    public function k($n): string
    {
        return 'K' . number_format((float) $n, 2);
    }

    private function date(?string $d): Carbon
    {
        try {
            return $d ? Carbon::parse($d)->startOfDay() : now('Africa/Lusaka')->startOfDay();
        } catch (\Throwable $e) {
            return now('Africa/Lusaka')->startOfDay();
        }
    }
}
