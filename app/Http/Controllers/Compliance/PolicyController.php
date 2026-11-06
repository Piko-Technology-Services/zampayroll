<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\Letterhead;
use App\Models\Compliance\PolicyAcknowledgement;
use App\Models\Employee;
use App\Services\Compliance\Approvals;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\DocxWriter;
use App\Services\Compliance\Policies;
use App\Services\Compliance\TemplateLibrary;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PolicyController extends Controller
{
    public function index(Request $request)
    {
        $q = CompanyPolicy::query()->orderByRaw("FIELD(status, 'changes', 'in_approval', 'draft', 'active', 'archived')")->orderBy('title');
        $filter = $request->query('filter');
        if ($filter === 'review') {
            $q->whereNotNull('active_version')->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30));
        } elseif ($filter && isset(CompanyPolicy::STATUSES[$filter])) {
            $q->where('status', $filter);
        } else {
            $q->where('status', '!=', 'archived');
        }
        $policies = $q->get();
        $ack = [];
        $workforce = Employee::workforce()->count();
        foreach ($policies as $p) {
            $ver = Policies::ackVersion($p);
            $ack[$p->id] = $ver ? ['done' => PolicyAcknowledgement::where('policy_id', $p->id)->where('version', '>=', $ver)->distinct('employee_id')->count('employee_id'), 'of' => $workforce] : null;
        }

        return view('compliance.policies.index', ['policies' => $policies, 'ack' => $ack, 'filter' => $filter, 'hasWorkflow' => (bool) Approvals::workflowFor('policy')]);
    }

    public function library()
    {
        $templates = DocumentTemplate::kind('policy')->where('is_active', true)->orderByRaw("FIELD(legal_status, 'required', 'conditional', 'recommended')")->orderBy('name')->get();
        $existing = CompanyPolicy::where('status', '!=', 'archived')->pluck('template_id')->filter()->all();

        return view('compliance.policies.library', ['templates' => $templates, 'existing' => $existing, 'letterheads' => Letterhead::orderByDesc('is_default')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['template_id' => 'required|integer', 'title' => 'nullable|string|max:160', 'letterhead_id' => 'nullable|integer']);
        $tpl = DocumentTemplate::kind('policy')->where('is_active', true)->findOrFail($d['template_id']);
        $lh = ! empty($d['letterhead_id']) ? Letterhead::findOrFail($d['letterhead_id']) : Letterhead::defaultForCompany();
        $p = Policies::create($tpl, $lh, $request->user(), $d['title'] ?? null);

        return redirect()->route('compliance.policies.edit', $p)->with('success', 'Policy drafted from the template with your company details. Edit it to fit how you work.');
    }

    public function show(CompanyPolicy $policy)
    {
        $workforce = Employee::workforce()->orderBy('first_name')->get();
        $ackVer = Policies::ackVersion($policy);
        $acks = $ackVer ? PolicyAcknowledgement::where('policy_id', $policy->id)->where('version', '>=', $ackVer)->get()->keyBy('employee_id') : collect();

        return view('compliance.policies.show', [
            'policy' => $policy, 'versions' => $policy->versions()->get(),
            'body' => Policies::renderedBody($policy, $policy->active_version && ! $policy->hasPendingRevision() ? $policy->active_version : null, true),
            'approvals' => $policy->approvals()->with('approver')->get(), 'workforce' => $workforce, 'acks' => $acks, 'ackVersion' => $ackVer,
            'hasWorkflow' => (bool) Approvals::workflowFor('policy'),
        ]);
    }

    public function edit(CompanyPolicy $policy)
    {
        abort_unless(Policies::isEditable($policy), 403, 'Start a revision to change an active policy.');
        $v = Policies::draft($policy);

        return view('compliance.policies.edit', ['policy' => $policy, 'version' => $v, 'letterheads' => Letterhead::orderByDesc('is_default')->get(),
            'values' => Policies::values($policy), 'hasWorkflow' => (bool) Approvals::workflowFor('policy')]);
    }

    public function update(Request $request, CompanyPolicy $policy)
    {
        $d = $request->validate([
            'title' => 'required|string|max:160', 'body' => 'required|string|max:400000', 'review_date' => 'nullable|date',
            'requires_acknowledgement' => 'nullable|boolean', 'letterhead_id' => 'nullable|integer', 'change_reason' => 'nullable|string|max:255',
        ]);
        $attrs = ['title' => $d['title'], 'review_date' => $d['review_date'] ?? null, 'requires_acknowledgement' => (bool) ($d['requires_acknowledgement'] ?? false)];
        if (! empty($d['letterhead_id'])) {
            $attrs['letterhead_id'] = Letterhead::findOrFail($d['letterhead_id'])->id;
        }
        Policies::saveDraft($policy, $d['body'], $attrs, $request->user(), $d['change_reason'] ?? null);

        if ($request->input('then') === 'submit') {
            return $this->submit($request, $policy->fresh());
        }

        return redirect()->route('compliance.policies.edit', $policy)->with('success', 'Draft saved.');
    }

    public function submit(Request $request, CompanyPolicy $policy)
    {
        $result = Policies::submit($policy, $request->user(), $request->boolean('fresh_ack', true));

        return redirect()->route('compliance.policies.show', $policy)->with('success', $result === 'published'
            ? 'Published. Employees with portal access have been asked to read and acknowledge it.'
            : 'Sent for approval. It becomes active once every step approves.');
    }

    public function revise(Request $request, CompanyPolicy $policy)
    {
        $d = $request->validate(['change_reason' => 'required|string|max:255']);
        Policies::revise($policy, $request->user(), $d['change_reason']);

        return redirect()->route('compliance.policies.edit', $policy)->with('success', 'Revision started. The current version stays active until the new one is approved.');
    }

    public function archive(CompanyPolicy $policy)
    {
        Approvals::cancelOpen('policy', $policy->id);
        $policy->update(['status' => 'archived']);
        Audit::record('compliance.policy_archived', $policy, "{$policy->reference} archived");

        return redirect()->route('compliance.policies.index')->with('success', 'Policy archived. Its versions and acknowledgements are kept.');
    }

    public function preview(Request $request, CompanyPolicy $policy)
    {
        return $this->pdfResponse($request, $policy, 'inline');
    }

    public function pdf(Request $request, CompanyPolicy $policy)
    {
        return $this->pdfResponse($request, $policy, 'attachment');
    }

    private function versionFor(Request $request, CompanyPolicy $policy): int
    {
        $v = (int) $request->query('version', $policy->current_version);
        abort_unless($v >= 1 && $v <= $policy->current_version, 404);

        return $v;
    }

    private function pdfResponse(Request $request, CompanyPolicy $policy, string $disposition)
    {
        $v = $this->versionFor($request, $policy);
        $draft = $v === $policy->current_version && Policies::isEditable($policy);
        $pdf = DocumentPdf::render(Policies::renderedBody($policy, $v, $draft), $policy->letterhead, Tenancy::company(), [
            'title' => $policy->title, 'watermark' => $draft ? 'DRAFT' : ((int) $policy->active_version === $v ? null : 'NOT CURRENT'),
            'footer_note' => $policy->reference . ' · v' . $v,
        ]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => $disposition . '; filename="' . $policy->reference . '-v' . $v . '.pdf"']);
    }

    public function docx(Request $request, CompanyPolicy $policy)
    {
        $v = $this->versionFor($request, $policy);
        $bytes = DocxWriter::make(Policies::renderedBody($policy, $v), $policy->letterhead, Tenancy::company(), $policy->title, $policy->reference . ' · v' . $v);

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . Str::slug($policy->title) . '-v' . $v . '.docx"']);
    }

    /** Acknowledgement status across all active policies. */
    public function acknowledgements()
    {
        $policies = CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->where('requires_acknowledgement', true)->orderBy('title')->get();
        $employees = Employee::workforce()->orderBy('first_name')->get();
        $matrix = [];
        foreach ($policies as $p) {
            $ver = Policies::ackVersion($p);
            $matrix[$p->id] = $ver ? PolicyAcknowledgement::where('policy_id', $p->id)->where('version', '>=', $ver)->pluck('acknowledged_at', 'employee_id') : collect();
        }

        return view('compliance.policies.acknowledgements', compact('policies', 'employees', 'matrix'));
    }
}
