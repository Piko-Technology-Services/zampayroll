<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Compliance\DocumentController;
use App\Http\Controllers\Controller;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyDocumentVersion;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\ComplianceComment;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\PolicyAcknowledgement;
use App\Services\Compliance\ComplianceMailer;
use App\Services\Compliance\Contracts;
use App\Services\Compliance\DocumentAccess;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\Policies;
use App\Services\Compliance\SignatureImage;
use App\Services\Compliance\Vault;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Employee self-service: My employment documents (contracts to review and sign, executed copies, shared documents)
 * and Company policies & notices (read and acknowledge). Every lookup is limited to the signed-in employee.
 */
class PortalDocumentController extends Controller
{
    public const EMPLOYEE_CONSENT = 'I have read this employment contract in full. I agree to its terms and I sign it electronically. I understand that my electronic signature has the same effect as my handwritten signature.';

    private function myContract(Request $request, EmploymentContract $contract): EmploymentContract
    {
        // Drafts are never shown to the employee; another employee's contract is a 404.
        abort_unless((int) $contract->employee_id === (int) $request->user()->employee_id && ! in_array($contract->status, ['draft', 'cancelled'], true), 404);

        return $contract;
    }

    private function visiblePolicy(CompanyPolicy $policy): CompanyPolicy
    {
        abort_unless($policy->active_version && $policy->status !== 'archived', 404);

        return $policy;
    }

    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        $contracts = EmploymentContract::where('employee_id', $employee->id)->whereNotIn('status', ['draft', 'cancelled'])->latest('id')->get();
        $policies = CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->orderBy('title')->get();
        $acked = PolicyAcknowledgement::where('employee_id', $employee->id)->get()->groupBy('policy_id')->map->max('version');
        $shared = DocumentAccess::scopeForEmployee(CompanyDocument::query(), $request->user())->with('latestVersion')->latest('id')->get()
            ->reject(fn ($d) => $d->contract_id && $contracts->contains('id', $d->contract_id));

        return view('portal.documents.index', compact('employee', 'contracts', 'policies', 'acked', 'shared'));
    }

    public function contract(Request $request, EmploymentContract $contract)
    {
        $this->myContract($request, $contract);
        Contracts::markViewed($contract);
        $version = Contracts::version($contract);

        return view('portal.documents.contract', [
            'contract' => $contract, 'body' => $version->body, 'version' => $version,
            'comments' => $contract->comments()->get(), 'consent' => self::EMPLOYEE_CONSENT,
            'mySignature' => $contract->signatures()->where('signer_type', 'employee')->where('version', $contract->current_version)->first(),
        ]);
    }

    public function contractPdf(Request $request, EmploymentContract $contract)
    {
        $this->myContract($request, $contract);
        if (in_array($contract->status, ['executed', 'superseded'], true) && $contract->executed_pdf_path) {
            $bytes = Vault::get($contract->executed_pdf_path);
            abort_unless($bytes !== null && hash_equals((string) $contract->executed_hash, hash('sha256', $bytes)), 410);
        } else {
            $bytes = DocumentPdf::render(Contracts::version($contract)->body, $contract->letterhead, Tenancy::company(),
                ['title' => $contract->title, 'watermark' => 'REVIEW COPY', 'footer_note' => $contract->reference . ' · v' . $contract->current_version]);
        }
        Audit::record('compliance.contract_downloaded_employee', $contract, "{$contract->reference} downloaded by the employee");

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $contract->reference . '.pdf"']);
    }

    public function sign(Request $request, EmploymentContract $contract)
    {
        $this->myContract($request, $contract);
        $d = $request->validate([
            'method' => 'required|in:draw,upload',
            'signature_data' => 'nullable|required_if:method,draw|string|max:2000000',
            'signature_file' => 'nullable|required_if:method,upload|file|max:5120|mimes:png,jpg,jpeg',
            'typed_name' => 'required|string|max:160',
            'version' => 'required|integer',
            'hash' => 'required|string|size:64',
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Tick the box to confirm you have read and agree to the contract.']);
        $png = $d['method'] === 'upload' ? SignatureImage::fromUpload($request->file('signature_file')) : SignatureImage::fromDataUrl($d['signature_data']);
        Contracts::employeeSign($contract, $request->user(), $d['method'], $png, $d['typed_name'], (int) $d['version'], $d['hash'], self::EMPLOYEE_CONSENT);

        return redirect()->route('portal.documents.contract', $contract)->with('success', 'Thank you, your contract is signed. It now goes to the company for approval and signature. You will receive the final copy by email.');
    }

    public function decline(Request $request, EmploymentContract $contract)
    {
        $this->myContract($request, $contract);
        $d = $request->validate(['reason' => 'required|string|max:2000']);
        Contracts::employeeDecline($contract, $request->user(), $d['reason']);

        return redirect()->route('portal.documents.index')->with('success', 'You declined the contract. HR has been told and will be in touch.');
    }

    public function comment(Request $request, EmploymentContract $contract)
    {
        $this->myContract($request, $contract);
        abort_unless($contract->status === 'sent', 422);
        $d = $request->validate(['body' => 'required|string|max:2000']);
        ComplianceComment::create(['subject_type' => 'contract', 'subject_id' => $contract->id, 'user_id' => $request->user()->id,
            'author_name' => $request->user()->name, 'author_role' => 'employee', 'body' => $d['body']]);
        Audit::record('compliance.contract_question', $contract, "Question from the employee on {$contract->reference}");
        ComplianceMailer::send('contract.question', Contracts::hrRecipients($contract), [
            'subject' => "Question about contract {$contract->reference}", 'eyebrow' => 'Employment contract', 'heading' => "{$contract->employee->full_name} has a question",
            'intro' => $d['body'], 'url' => route('compliance.contracts.show', $contract), 'label' => 'Reply in ZamPayroll',
        ], 'contract', $contract->id);

        return back()->with('success', 'Your question was sent to HR.');
    }

    public function policy(Request $request, CompanyPolicy $policy)
    {
        $this->visiblePolicy($policy);
        $v = $policy->activeVersionRow();
        $ackVersion = Policies::ackVersion($policy);
        $mine = PolicyAcknowledgement::where('policy_id', $policy->id)->where('employee_id', $request->user()->employee_id)->orderByDesc('version')->first();

        return view('portal.documents.policy', ['policy' => $policy, 'version' => $v, 'needsAck' => $ackVersion && (! $mine || $mine->version < $ackVersion),
            'mine' => $mine, 'statement' => PolicyAcknowledgement::STATEMENT]);
    }

    public function policyPdf(Request $request, CompanyPolicy $policy)
    {
        $this->visiblePolicy($policy);
        $v = $policy->activeVersionRow();
        $pdf = DocumentPdf::render($v->body, $policy->letterhead, Tenancy::company(), ['title' => $policy->title, 'footer_note' => $policy->reference . ' · v' . $v->version]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $policy->reference . '.pdf"']);
    }

    public function acknowledge(Request $request, CompanyPolicy $policy)
    {
        $this->visiblePolicy($policy);
        $d = $request->validate(['version' => 'required|integer', 'hash' => 'required|string|size:64', 'confirm' => 'accepted'],
            ['confirm.accepted' => 'Tick the box to confirm you have read and understood the policy.']);
        Policies::acknowledge($policy, $request->user(), (int) $d['version'], $d['hash']);

        return redirect()->route('portal.documents.index')->with('success', 'Thank you. Your acknowledgement has been recorded.');
    }

    public function file(Request $request, CompanyDocument $document)
    {
        abort_unless(DocumentAccess::canEmployeeView($request->user(), $document), 404);
        $v = CompanyDocumentVersion::where('document_id', $document->id)->where('version', $document->current_version)->firstOrFail();

        return DocumentController::serve($document, $v, $request->boolean('inline') && $v->previewable() ? 'inline' : 'attachment');
    }
}
