<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceComment;
use App\Models\Compliance\ComplianceMailLog;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\Letterhead;
use App\Models\Employee;
use App\Services\Compliance\Approvals;
use App\Services\Compliance\ComplianceMailer;
use App\Services\Compliance\Contracts;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\DocxWriter;
use App\Services\Compliance\Placeholders;
use App\Services\Compliance\TemplateLibrary;
use App\Services\Compliance\Vault;
use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = EmploymentContract::with('employee')->latest('id');
        if ($status === 'returned') {
            $q->whereIn('status', ['declined', 'changes', 'rejected']);
        } elseif ($status && isset(EmploymentContract::STATUSES[$status])) {
            $q->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $q->where('contract_type', $type);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%")));
        }
        $counts = EmploymentContract::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return view('compliance.contracts.index', ['contracts' => $q->paginate(25)->withQueryString(), 'status' => $status, 'counts' => $counts]);
    }

    public function create(Request $request)
    {
        return view('compliance.contracts.create', [
            'employees' => Employee::workforce()->orderBy('first_name')->get(),
            'templates' => DocumentTemplate::kind('contract')->where('is_active', true)->orderByRaw('company_id IS NULL')->orderBy('name')->get(),
            'letterheads' => Letterhead::orderByDesc('is_default')->orderBy('name')->get(),
            'selected' => $request->query('employee'),
            'workflowReady' => (bool) Approvals::workflowFor('contract'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'employee_id' => 'required|integer',
            'contract_type' => 'required|in:' . implode(',', array_keys(TemplateLibrary::CONTRACT_TYPES)),
            'template_id' => 'required|integer',
            'letterhead_id' => 'nullable|integer',
        ]);
        $employee = Employee::findOrFail($d['employee_id']);
        $template = DocumentTemplate::kind('contract')->where('is_active', true)->findOrFail($d['template_id']);
        $lh = ! empty($d['letterhead_id']) ? Letterhead::findOrFail($d['letterhead_id']) : Letterhead::defaultForCompany();
        $contract = Contracts::create($employee, $d['contract_type'], $template, $lh, $request->user());

        return redirect()->route('compliance.contracts.edit', $contract)->with('success', 'Draft created from ' . $template->name . '. Details already in ZamPayroll have been filled in.');
    }

    public function show(EmploymentContract $contract)
    {
        $contract->load(['employee', 'versions', 'signatures', 'creator', 'supersedes']);

        return view('compliance.contracts.show', [
            'contract' => $contract,
            'body' => Contracts::renderedBody($contract, true),
            'missing' => $contract->isEditable() ? Contracts::missing($contract) : [],
            'approvals' => $contract->approvals()->with('approver')->get(),
            'comments' => $contract->comments()->get(),
            'mails' => ComplianceMailLog::where('subject_type', 'contract')->where('subject_id', $contract->id)->latest('id')->limit(30)->get(),
            'history' => AuditLog::where('company_id', Tenancy::companyId())->where('subject_type', 'EmploymentContract')->where('subject_id', $contract->id)->orderBy('id')->get(),
            'workflow' => Approvals::workflowFor('contract', $contract->contract_type),
        ]);
    }

    public function edit(EmploymentContract $contract)
    {
        abort_unless($contract->isEditable(), 403, 'This contract can no longer be edited. Create an amendment instead.');
        $version = Contracts::version($contract);
        $values = Contracts::values($contract);
        $used = Placeholders::used($version->body);

        return view('compliance.contracts.edit', [
            'contract' => $contract->load('employee'),
            'version' => $version,
            'frozen' => Contracts::isFrozen($version),
            'used' => $used,
            'values' => $values,
            'data' => $contract->data ?? [],
            'missing' => Contracts::missing($contract),
            'letterheads' => Letterhead::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, EmploymentContract $contract)
    {
        $d = $request->validate([
            'body' => 'required|string|max:400000',
            'data' => 'nullable|array',
            'letterhead_id' => 'nullable|integer',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'change_note' => 'nullable|string|max:255',
        ]);
        if (! empty($d['letterhead_id'])) {
            $contract->letterhead_id = Letterhead::findOrFail($d['letterhead_id'])->id;
        }
        $contract->start_date = $d['start_date'] ?? $contract->start_date;
        $contract->end_date = $d['end_date'] ?? $contract->end_date;
        Contracts::saveDraft($contract, $d['body'], $d['data'] ?? [], $request->user(), $d['change_note'] ?? null);

        if ($request->input('then') === 'send') {
            return $this->send($request, $contract->fresh());
        }

        return redirect()->route('compliance.contracts.edit', $contract)->with('success', 'Draft saved.');
    }

    /** Inline PDF of the current version on the letterhead (draft watermark until executed). */
    public function preview(EmploymentContract $contract)
    {
        return $this->pdfResponse($contract, 'inline');
    }

    public function pdf(EmploymentContract $contract)
    {
        return $this->pdfResponse($contract, 'attachment');
    }

    private function pdfResponse(EmploymentContract $contract, string $disposition)
    {
        if ($contract->executed_pdf_path && in_array($contract->status, ['executed', 'superseded'], true)) {
            $bytes = Vault::get($contract->executed_pdf_path);
            abort_unless($bytes !== null && hash_equals((string) $contract->executed_hash, hash('sha256', $bytes)), 410, 'The stored contract file failed its integrity check. Contact support.');
            Audit::record('compliance.contract_downloaded', $contract, "{$contract->reference} executed PDF opened");
        } else {
            $bytes = DocumentPdf::render(Contracts::renderedBody($contract, $contract->isEditable()), $contract->letterhead, Tenancy::company(), [
                'title' => $contract->title, 'watermark' => $contract->isEditable() ? 'DRAFT' : 'REVIEW COPY',
                'footer_note' => $contract->reference . ' · v' . $contract->current_version,
            ]);
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => $disposition . '; filename="' . $contract->reference . '.pdf"']);
    }

    public function docx(EmploymentContract $contract)
    {
        $bytes = DocxWriter::make(Contracts::renderedBody($contract, $contract->isEditable()), $contract->letterhead, Tenancy::company(), $contract->title,
            $contract->reference . ' · v' . $contract->current_version);
        Audit::record('compliance.contract_docx', $contract, "{$contract->reference} Word copy downloaded");

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $contract->reference . '-' . Str::slug($contract->employee->full_name) . '.docx"',
        ]);
    }

    public function send(Request $request, EmploymentContract $contract)
    {
        Contracts::send($contract, $request->user());

        return redirect()->route('compliance.contracts.show', $contract)->with('success', "Sent to {$contract->employee->full_name}. They will review and sign it in their portal.");
    }

    public function comment(Request $request, EmploymentContract $contract)
    {
        $d = $request->validate(['body' => 'required|string|max:2000']);
        ComplianceComment::create(['subject_type' => 'contract', 'subject_id' => $contract->id, 'user_id' => $request->user()->id,
            'author_name' => $request->user()->name, 'author_role' => 'hr', 'body' => $d['body']]);
        if ($contract->status === 'sent') {
            ComplianceMailer::send('contract.reply', [$contract->employee->user?->email ?? $contract->employee->portal_email], [
                'subject' => 'HR replied about your contract ' . $contract->reference, 'eyebrow' => 'Employment contract', 'heading' => 'You have a reply from HR',
                'intro' => 'Sign in to your portal to read the reply.', 'url' => route('portal.documents.contract', $contract), 'label' => 'Open my contract',
            ], 'contract', $contract->id);
        }

        return back()->with('success', 'Comment added.');
    }

    public function cancel(Request $request, EmploymentContract $contract)
    {
        abort_if(in_array($contract->status, ['executed', 'superseded', 'cancelled'], true), 422, 'An executed contract cannot be cancelled. Create an amendment or a replacement instead.');
        Approvals::cancelOpen('contract', $contract->id);
        $contract->update(['status' => 'cancelled']);
        Audit::record('compliance.contract_cancelled', $contract, "{$contract->reference} cancelled", ['reason' => $request->input('reason')]);

        return redirect()->route('compliance.contracts.show', $contract)->with('success', 'Contract cancelled. It stays on record.');
    }

    /** Executed contracts are never edited: start an amendment that goes through signing and approval again. */
    public function amend(Request $request, EmploymentContract $contract)
    {
        abort_unless($contract->status === 'executed', 422);
        $tpl = $contract->template ?? DocumentTemplate::kind('contract')->where('key', $contract->contract_type)->firstOrFail();
        $new = Contracts::create($contract->employee, $contract->contract_type, $tpl, $contract->letterhead, $request->user(), $contract, Contracts::version($contract)->body);

        return redirect()->route('compliance.contracts.edit', $new)->with('success', "Amendment {$new->reference} started from the executed text. The original stays valid until this one is fully signed.");
    }
}
