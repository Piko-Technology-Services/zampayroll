<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\PolicyAcknowledgement;
use App\Models\Employee;
use App\Services\Compliance\DocumentAccess;
use App\Services\Compliance\Policies;
use App\Services\Compliance\TableExport;
use App\Services\Compliance\TemplateLibrary;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Contract, policy and repository reports, each exportable to Excel, CSV or PDF. */
class ReportController extends Controller
{
    public const REPORTS = [
        'Employment contracts' => [
            'with_contract' => 'Employees with completed contracts',
            'without_contract' => 'Employees without recorded contracts',
            'awaiting_signature' => 'Contracts awaiting the employee',
            'awaiting_approval' => 'Contracts awaiting approval',
            'rejected' => 'Rejected, declined or returned contracts',
            'expired' => 'Expired fixed-term contracts',
            'fixed_term_ending' => 'Fixed-term contracts ending in 60 days',
            'execution_history' => 'Contract execution history',
        ],
        'Policies' => [
            'active_policies' => 'Active policies',
            'policies_awaiting' => 'Policies awaiting approval',
            'policies_review' => 'Policies due for review',
            'ack_status' => 'Employee acknowledgement status',
            'policy_versions' => 'Policy version history',
        ],
        'Document repository' => [
            'docs_by_category' => 'Documents by category',
            'docs_by_department' => 'Documents by department',
            'docs_by_employee' => 'Documents by employee',
            'docs_expiring' => 'Documents expiring in 30 days',
            'docs_expired' => 'Expired documents',
            'docs_history' => 'Upload and change history',
        ],
    ];

    private function title(string $key): string
    {
        foreach (self::REPORTS as $group) {
            if (isset($group[$key])) {
                return $group[$key];
            }
        }
        abort(404);
    }

    public function index(Request $request)
    {
        $key = $request->query('report', 'without_contract');
        [$columns, $rows] = $this->build($key, $request);

        return view('compliance.reports.index', ['reports' => self::REPORTS, 'key' => $key, 'title' => $this->title($key), 'columns' => $columns, 'rows' => $rows]);
    }

    public function export(Request $request)
    {
        $key = (string) $request->query('report');
        [$columns, $rows] = $this->build($key, $request);
        \App\Support\Audit::record('compliance.report_exported', null, $this->title($key) . ' exported', ['format' => $request->query('format')]);

        return TableExport::download($this->title($key), $columns, $rows, in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true) ? $request->query('format') : 'xlsx');
    }

    private function d($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    private function build(string $key, Request $request): array
    {
        $this->title($key);
        $type = fn ($t) => TemplateLibrary::CONTRACT_TYPES[$t] ?? $t;
        $contractRows = fn ($q) => $q->with('employee')->get()->map(fn (EmploymentContract $c) => [
            $c->reference, $c->employee?->full_name, $c->employee?->employee_id, $type($c->contract_type), $c->statusLabel(), 'v' . $c->current_version,
            $this->d($c->start_date), $this->d($c->end_date), $c->sent_at?->format('Y-m-d H:i'), $c->executed_at?->format('Y-m-d H:i'),
        ])->all();
        $contractCols = ['Reference', 'Employee', 'Employee no.', 'Type', 'Status', 'Version', 'Start', 'End', 'Sent', 'Executed'];

        switch ($key) {
            case 'with_contract':
                return [$contractCols, $contractRows(EmploymentContract::where('status', 'executed')->orderBy('reference'))];
            case 'without_contract':
                $executed = EmploymentContract::where('status', 'executed')->pluck('employee_id');
                $uploaded = CompanyDocument::whereNotNull('employee_id')->where('status', 'active')->whereHas('category', fn ($q) => $q->where('name', 'Employment contracts'))->pluck('employee_id');
                $open = EmploymentContract::whereNotIn('status', ['executed', 'superseded', 'cancelled'])->get()->keyBy('employee_id');

                return [['Employee', 'Employee no.', 'Position', 'Department', 'Contract in progress'], Employee::workforce()->whereNotIn('id', $executed->merge($uploaded))
                    ->orderBy('first_name')->get()->map(fn ($e) => [$e->full_name, $e->employee_id, $e->position, $e->department,
                        isset($open[$e->id]) ? $open[$e->id]->reference . ' (' . $open[$e->id]->statusLabel() . ')' : 'None'])->all()];
            case 'awaiting_signature':
                return [$contractCols, $contractRows(EmploymentContract::where('status', 'sent')->orderBy('sent_at'))];
            case 'awaiting_approval':
                return [$contractCols, $contractRows(EmploymentContract::where('status', 'in_approval')->orderBy('employee_signed_at'))];
            case 'rejected':
                return [$contractCols, $contractRows(EmploymentContract::whereIn('status', ['declined', 'changes', 'rejected'])->latest('updated_at'))];
            case 'expired':
                return [$contractCols, $contractRows(EmploymentContract::where('status', 'executed')->whereNotNull('end_date')->where('end_date', '<', now()->startOfDay())->orderBy('end_date'))];
            case 'fixed_term_ending':
                return [$contractCols, $contractRows(EmploymentContract::where('status', 'executed')->whereNotNull('end_date')->whereBetween('end_date', [now()->startOfDay(), now()->addDays(60)])->orderBy('end_date'))];
            case 'execution_history':
                return [['When', 'Contract', 'Event', 'By'], AuditLog::where('company_id', Tenancy::companyId())->where('subject_type', 'EmploymentContract')
                    ->where('action', 'like', 'compliance.contract_%')->latest('id')->limit(1000)->get()
                    ->map(fn ($a) => [$a->created_at->format('Y-m-d H:i'), $a->summary, str_replace(['compliance.contract_', '_'], ['', ' '], $a->action), $a->user_name])->all()];

            case 'active_policies':
            case 'policies_awaiting':
            case 'policies_review':
                $q = CompanyPolicy::query()->orderBy('title');
                $key === 'active_policies' ? $q->whereNotNull('active_version')->where('status', '!=', 'archived')
                    : ($key === 'policies_awaiting' ? $q->where('status', 'in_approval') : $q->whereNotNull('active_version')->where('status', '!=', 'archived')->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30)));

                return [['Reference', 'Policy', 'Legal status', 'Status', 'Active version', 'Published', 'Review date'], $q->get()->map(fn ($p) => [
                    $p->reference, $p->title, TemplateLibrary::LEGAL_STATUS[$p->legal_status] ?? '—', $p->statusLabel(), $p->active_version ? 'v' . $p->active_version : '—',
                    $p->published_at?->format('Y-m-d'), $this->d($p->review_date)])->all()];
            case 'ack_status':
                $rows = [];
                foreach (CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->where('requires_acknowledgement', true)->orderBy('title')->get() as $p) {
                    $ver = Policies::ackVersion($p);
                    $acks = $ver ? PolicyAcknowledgement::where('policy_id', $p->id)->where('version', '>=', $ver)->get()->keyBy('employee_id') : collect();
                    foreach (Employee::workforce()->orderBy('first_name')->get() as $e) {
                        $a = $acks[$e->id] ?? null;
                        $rows[] = [$p->title, 'v' . $ver, $e->full_name, $e->department, $a ? 'Acknowledged' : 'Outstanding', $a?->acknowledged_at?->format('Y-m-d H:i')];
                    }
                }

                return [['Policy', 'Version', 'Employee', 'Department', 'Status', 'Acknowledged'], $rows];
            case 'policy_versions':
                $rows = [];
                foreach (CompanyPolicy::with('versions')->orderBy('title')->get() as $p) {
                    foreach ($p->versions as $v) {
                        $rows[] = [$p->title, 'v' . $v->version, ucfirst($v->status), $v->change_reason, $v->published_at?->format('Y-m-d'), $v->created_at->format('Y-m-d')];
                    }
                }

                return [['Policy', 'Version', 'Status', 'Reason', 'Published', 'Created'], $rows];

            default:
                $q = DocumentAccess::scopeForStaff(CompanyDocument::query(), $request->user())->with(['category.parent', 'employee'])->where('status', 'active');
                $docRow = fn ($d) => [$d->title, $d->reference, $d->category?->fullName(), $d->employee?->full_name, $d->department, $this->d($d->expiry_date), $this->d($d->review_date), 'v' . $d->current_version];
                $docCols = ['Title', 'Reference', 'Category', 'Employee', 'Department', 'Expires', 'Review', 'Version'];

                return match ($key) {
                    'docs_by_category' => [$docCols, $q->get()->sortBy(fn ($d) => $d->category?->fullName() . $d->title)->map($docRow)->values()->all()],
                    'docs_by_department' => [$docCols, $q->get()->sortBy(fn ($d) => ($d->department ?? 'zz') . $d->title)->map($docRow)->values()->all()],
                    'docs_by_employee' => [$docCols, $q->whereNotNull('employee_id')->get()->sortBy(fn ($d) => $d->employee?->full_name . $d->title)->map($docRow)->values()->all()],
                    'docs_expiring' => [$docCols, $q->whereNotNull('expiry_date')->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)])->orderBy('expiry_date')->get()->map($docRow)->all()],
                    'docs_expired' => [$docCols, $q->whereNotNull('expiry_date')->where('expiry_date', '<', now()->startOfDay())->orderBy('expiry_date')->get()->map($docRow)->all()],
                    'docs_history' => [['When', 'Event', 'By'], AuditLog::where('company_id', Tenancy::companyId())->where('subject_type', 'CompanyDocument')->latest('id')->limit(1000)->get()
                        ->map(fn ($a) => [$a->created_at->format('Y-m-d H:i'), $a->summary, $a->user_name])->all()],
                };
        }
    }
}
