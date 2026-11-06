<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\ApprovalRequest;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyDocumentVersion;
use App\Models\Compliance\ComplianceSetting;
use App\Models\Compliance\ContractSignature;
use App\Models\Compliance\ContractVersion;
use App\Models\Compliance\DocumentCategory;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\Letterhead;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The employment contract lifecycle:
 * draft → sent (employee reviews) → in_approval (after the employee signs) → executed (after the final company signature).
 * Declines, rejections and change requests send it back to HR; editing then creates a new version and the employee signs again.
 */
class Contracts
{
    public static function company(): Company
    {
        return Tenancy::company();
    }

    public static function nextReference(): string
    {
        $prefix = 'CT-' . now()->format('Y') . '-';
        $last = EmploymentContract::withTrashed()->where('reference', 'like', $prefix . '%')->orderByDesc('id')->value('reference');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function create(Employee $employee, string $type, DocumentTemplate $template, ?Letterhead $letterhead, User $user, ?EmploymentContract $supersedes = null, ?string $body = null): EmploymentContract
    {
        abort_unless(isset(TemplateLibrary::CONTRACT_TYPES[$type]) && $template->kind === 'contract', 422);

        return DB::transaction(function () use ($employee, $type, $template, $letterhead, $user, $supersedes, $body) {
            $contract = EmploymentContract::create([
                'employee_id' => $employee->id, 'template_id' => $template->id, 'letterhead_id' => $letterhead?->id,
                'supersedes_id' => $supersedes?->id, 'reference' => self::nextReference(), 'contract_type' => $type,
                'title' => (TemplateLibrary::CONTRACT_TYPES[$type] ?? 'Employment contract') . ' · ' . $employee->full_name,
                'status' => 'draft', 'current_version' => 1, 'start_date' => $employee->contract_start, 'end_date' => $employee->contract_end,
                'data' => [], 'created_by' => $user->id,
            ]);
            ContractVersion::create(['contract_id' => $contract->id, 'version' => 1, 'body' => HtmlSanitizer::clean($body ?? $template->body),
                'hash' => '', 'change_note' => $supersedes ? 'Amendment of ' . $supersedes->reference : 'Created from ' . $template->name, 'created_by' => $user->id]);
            Audit::record('compliance.contract_created', $contract, "{$contract->reference} created for {$employee->full_name}", ['template' => $template->name]);

            return $contract;
        });
    }

    public static function values(EmploymentContract $c): array
    {
        $company = self::company();
        $settings = ComplianceSetting::current();
        $values = array_merge(Placeholders::company($company, $settings), Placeholders::employee($c->employee, $company, $c->contract_type, $settings));
        // Final company signatory defaults to the person on the workflow's signing step.
        $flow = Approvals::workflowFor('contract', $c->contract_type);
        $signStep = $flow?->steps->where('action', 'sign')->last();
        if ($signStep && $signStep->approver_type === 'user' && $signStep->approver) {
            $values['signatory_name'] = $values['signatory_name'] ?: $signStep->approver->name;
            $values['signatory_title'] = $values['signatory_title'] ?: ($signStep->approver->employee?->position ?? $signStep->label);
        }
        foreach (($c->data ?? []) as $k => $v) {
            if ($v !== null && trim((string) $v) !== '') {
                $values[$k] = $v;
            }
        }

        return $values;
    }

    public static function version(EmploymentContract $c, ?int $v = null): ContractVersion
    {
        return ContractVersion::where('contract_id', $c->id)->where('version', $v ?? $c->current_version)->firstOrFail();
    }

    public static function isFrozen(ContractVersion $v): bool
    {
        return $v->hash !== '';
    }

    /** Body for screens and PDFs: placeholders filled (highlighted when $highlight). */
    public static function renderedBody(EmploymentContract $c, bool $highlight = false, ?int $version = null): string
    {
        $v = self::version($c, $version);

        return self::isFrozen($v) ? $v->body : Placeholders::fill($v->body, self::values($c), $highlight);
    }

    public static function missing(EmploymentContract $c): array
    {
        $v = self::version($c);

        return self::isFrozen($v) ? [] : Placeholders::missing($v->body, self::values($c));
    }

    /** Save HR's edits. Once a version has been sent it is never changed: a new version is created instead. */
    public static function saveDraft(EmploymentContract $c, string $body, array $data, User $user, ?string $note = null): void
    {
        abort_unless($c->isEditable(), 422, 'This contract can no longer be edited. Create an amendment instead.');
        $body = HtmlSanitizer::clean($body);
        $data = array_intersect_key(array_map(fn ($v) => is_string($v) ? mb_substr(trim($v), 0, 4000) : null, $data), TemplateLibrary::PLACEHOLDERS);

        DB::transaction(function () use ($c, $body, $data, $user, $note) {
            $current = self::version($c);
            if (self::isFrozen($current)) {
                $c->current_version++;
                ContractVersion::create(['contract_id' => $c->id, 'version' => $c->current_version, 'body' => $body, 'hash' => '',
                    'change_note' => $note ?: 'Edited after review', 'created_by' => $user->id]);
            } else {
                $current->update(['body' => $body, 'change_note' => $note ?: $current->change_note]);
            }
            $c->data = array_merge($c->data ?? [], $data);
            $c->save();
        });
        Audit::record('compliance.contract_edited', $c, "{$c->reference} edited (version {$c->current_version})", ['note' => $note]);
    }

    /** Freeze the current version, then send it to the employee. */
    public static function send(EmploymentContract $c, User $user): void
    {
        abort_unless($c->isEditable(), 422);
        $employee = $c->employee;
        if (! $employee->hasPortalAccess()) {
            throw ValidationException::withMessages(['send' => "{$employee->full_name} has no employee portal account yet. Give them portal access (Team › the employee › Portal) so they can sign in, read and sign the contract."]);
        }
        if ($missing = self::missing($c)) {
            throw ValidationException::withMessages(['send' => 'Fill in the missing details first: ' . implode(', ', array_map([Placeholders::class, 'label'], $missing)) . '.']);
        }
        $flow = Approvals::workflowFor('contract', $c->contract_type);
        if (! $flow || ! $flow->steps->contains('action', 'sign')) {
            throw ValidationException::withMessages(['send' => 'Set up a contract approval workflow with a final company signatory first (Compliance Centre › Settings › Approval workflows).']);
        }

        DB::transaction(function () use ($c) {
            $v = self::version($c);
            if (! self::isFrozen($v)) {
                $body = Placeholders::fill($v->body, self::values($c));
                $v->update(['body' => $body, 'hash' => hash('sha256', $body)]);
            }
            Approvals::cancelOpen('contract', $c->id);
            $c->update(['status' => 'sent', 'sent_at' => now(), 'employee_viewed_at' => null, 'employee_signed_at' => null, 'declined_at' => null, 'decline_reason' => null]);
        });
        Audit::record('compliance.contract_sent', $c, "{$c->reference} v{$c->current_version} sent to {$employee->full_name}");

        ComplianceMailer::send('contract.issued', [$employee->user?->email ?? $employee->portal_email], [
            'subject' => 'Your employment contract from ' . self::company()->name . ' is ready to review',
            'eyebrow' => 'Employment contract', 'heading' => 'Please review your contract',
            'intro' => self::company()->name . " has sent you an employment contract. Sign in to your ZamPayroll portal to read it, ask questions, and accept and sign it, or decline it.\n\nOpening or downloading the contract does not mean you have accepted it.",
            'rows' => ['Employer' => self::company()->name, 'Contract' => TemplateLibrary::CONTRACT_TYPES[$c->contract_type] ?? $c->contract_type, 'Reference' => $c->reference],
            'url' => route('portal.documents.contract', $c), 'label' => 'Sign in to review',
        ], 'contract', $c->id);
    }

    public static function markViewed(EmploymentContract $c): void
    {
        if (! $c->employee_viewed_at && $c->status === 'sent') {
            $c->update(['employee_viewed_at' => now()]);
            Audit::record('compliance.contract_viewed', $c, "{$c->reference} opened by the employee");
        }
    }

    /** The employee signs the exact version and fingerprint they were shown. */
    public static function employeeSign(EmploymentContract $c, User $user, string $method, string $png, string $typedName, int $version, string $hash, string $consent): void
    {
        abort_unless($c->status === 'sent' && (int) $user->employee_id === (int) $c->employee_id, 403);
        $v = self::version($c);
        if ($version !== (int) $c->current_version || ! hash_equals($v->hash, $hash)) {
            throw ValidationException::withMessages(['signature' => 'This contract changed while you were reading it. Please reload the page and read the latest version.']);
        }
        if (mb_strtolower(trim(preg_replace('/\s+/', ' ', $typedName))) !== mb_strtolower(trim(preg_replace('/\s+/', ' ', $c->employee->full_name)))) {
            throw ValidationException::withMessages(['typed_name' => 'Type your full name exactly as it appears on the contract: ' . $c->employee->full_name . '.']);
        }
        $flow = Approvals::workflowFor('contract', $c->contract_type);
        abort_unless($flow, 422, 'The company has not set up its approval workflow. Please contact HR.');

        DB::transaction(function () use ($c, $user, $method, $png, $typedName, $v, $consent, $flow) {
            ContractSignature::create([
                'contract_id' => $c->id, 'version' => $c->current_version, 'signer_type' => 'employee', 'user_id' => $user->id, 'employee_id' => $c->employee_id,
                'signer_name' => $c->employee->full_name, 'signer_title' => $c->employee->position, 'method' => $method,
                'image_path' => Vault::putContents($png, 'signatures', 'png'), 'document_hash' => $v->hash, 'consent_text' => $consent,
                'typed_name' => $typedName, 'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255), 'signed_at' => now(),
            ]);
            $c->update(['status' => 'in_approval', 'employee_signed_at' => now(), 'round' => $c->round + 1]);
            $first = Approvals::start('contract', $c->id, $flow, $c->current_version, $c->round);
            self::notifyStep($c, $first);
        });
        Audit::record('compliance.contract_signed_employee', $c, "{$c->reference} v{$c->current_version} signed by {$c->employee->full_name} ({$method})",
            ['hash' => $v->hash, 'ip' => request()->ip()]);

        ComplianceMailer::send('contract.employee_signed', self::hrRecipients($c), [
            'subject' => "{$c->employee->full_name} signed contract {$c->reference}", 'eyebrow' => 'Employment contract',
            'heading' => 'The employee has signed', 'intro' => "{$c->employee->full_name} accepted and signed the contract. It is now with the approvers.",
            'rows' => ['Reference' => $c->reference, 'Version' => (string) $c->current_version], 'url' => route('compliance.contracts.show', $c), 'label' => 'View contract',
        ], 'contract', $c->id);
    }

    public static function employeeDecline(EmploymentContract $c, User $user, string $reason): void
    {
        abort_unless($c->status === 'sent' && (int) $user->employee_id === (int) $c->employee_id, 403);
        $c->update(['status' => 'declined', 'declined_at' => now(), 'decline_reason' => $reason]);
        Audit::record('compliance.contract_declined', $c, "{$c->reference} declined by the employee", ['reason' => $reason]);
        ComplianceMailer::send('contract.declined', self::hrRecipients($c), [
            'subject' => "{$c->employee->full_name} declined contract {$c->reference}", 'eyebrow' => 'Employment contract',
            'heading' => 'Contract declined', 'intro' => "Reason given: {$reason}", 'url' => route('compliance.contracts.show', $c), 'label' => 'View contract',
        ], 'contract', $c->id);
    }

    /** An approver acts. A signing step must include the signer's signature. */
    public static function decide(EmploymentContract $c, ApprovalRequest $req, User $user, string $decision, ?string $comment, ?array $signature = null): string
    {
        abort_unless($c->status === 'in_approval' && $req->approvable_type === 'contract' && (int) $req->approvable_id === (int) $c->id
            && (int) $req->version === (int) $c->current_version, 422);
        if ((int) $user->employee_id === (int) $c->employee_id) {
            abort(403, 'You cannot approve your own contract.');
        }
        if ($decision !== 'approve' && trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Please give a reason so HR knows what to change.']);
        }
        if ($decision === 'approve' && $req->action === 'sign' && ! $signature) {
            throw ValidationException::withMessages(['signature' => 'Add your signature to sign for the company.']);
        }

        $result = DB::transaction(function () use ($c, $req, $user, $decision, $comment, $signature) {
            $r = Approvals::decide($req, $user, $decision, $comment);
            if ($decision === 'approve' && $req->action === 'sign') {
                $v = self::version($c);
                ContractSignature::create([
                    'contract_id' => $c->id, 'version' => $c->current_version, 'signer_type' => 'company', 'user_id' => $user->id, 'approval_id' => $req->id,
                    'signer_name' => $user->name, 'signer_title' => $signature['title'] ?: ($user->employee?->position ?? $req->label), 'method' => $signature['method'],
                    'image_path' => Vault::putContents($signature['png'], 'signatures', 'png'), 'document_hash' => $v->hash, 'consent_text' => $signature['consent'],
                    'typed_name' => $user->name, 'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255), 'signed_at' => now(),
                ]);
            }
            if ($r['outcome'] === 'returned') {
                $c->update(['status' => $decision === 'reject' ? 'rejected' : 'changes']);
            }

            return $r;
        });

        if ($result['outcome'] === 'next') {
            self::notifyStep($c, $result['next']);
        } elseif ($result['outcome'] === 'complete') {
            self::execute($c->fresh());
        } else {
            ComplianceMailer::send($decision === 'reject' ? 'contract.rejected' : 'contract.changes', self::hrRecipients($c), [
                'subject' => ($decision === 'reject' ? 'Contract rejected: ' : 'Changes requested: ') . $c->reference, 'eyebrow' => 'Employment contract',
                'heading' => $decision === 'reject' ? 'An approver rejected the contract' : 'An approver asked for changes',
                'intro' => "{$user->name} ({$req->label}): {$comment}\n\nEdit the contract to create a new version, then send it to the employee again. The approvals restart.",
                'rows' => ['Reference' => $c->reference, 'Employee' => $c->employee->full_name], 'url' => route('compliance.contracts.show', $c), 'label' => 'Open contract',
            ], 'contract', $c->id);
        }

        return $result['outcome'];
    }

    public static function notifyStep(EmploymentContract $c, ?ApprovalRequest $req): void
    {
        if (! $req) {
            return;
        }
        ComplianceMailer::send($req->action === 'sign' ? 'contract.final_signature' : 'contract.awaiting_approval', Approvals::recipients($req), [
            'subject' => ($req->action === 'sign' ? 'Final company signature needed: ' : 'Contract awaiting your approval: ') . $c->reference,
            'eyebrow' => 'Approval needed', 'heading' => $req->action === 'sign' ? 'Please sign for the company' : 'A contract is waiting for you',
            'intro' => "Step: {$req->label}. The employee has already signed. Sign in to review and act.",
            'rows' => ['Employee' => $c->employee->full_name, 'Contract' => TemplateLibrary::CONTRACT_TYPES[$c->contract_type] ?? '', 'Reference' => $c->reference],
            'url' => route('compliance.approvals.show', $req), 'label' => 'Review now',
        ], 'contract', $c->id);
    }

    /** Build, fingerprint, store and distribute the executed contract. */
    public static function execute(EmploymentContract $c): void
    {
        $company = self::company();
        $v = self::version($c);
        $pdf = DocumentPdf::render($v->body, $c->letterhead, $company, [
            'title' => $c->title, 'footer_note' => $c->reference . ' · v' . $c->current_version,
            'append' => DocumentPdf::contractSignaturePage($c, $v->hash),
        ]);
        $hash = hash('sha256', $pdf);
        $path = Vault::putContents($pdf, 'contracts', 'pdf');

        DB::transaction(function () use ($c, $path, $hash, $pdf) {
            $c->update(['status' => 'executed', 'executed_at' => now(), 'executed_pdf_path' => $path, 'executed_hash' => $hash]);
            if ($c->supersedes_id) {
                EmploymentContract::whereKey($c->supersedes_id)->where('status', 'executed')->update(['status' => 'superseded']);
            }
            // File the executed contract in the employee's records in the repository.
            DocumentCategory::ensureDefaults();
            $cat = DocumentCategory::where('name', 'Employment contracts')->first();
            $doc = CompanyDocument::create([
                'category_id' => $cat?->id, 'title' => $c->title, 'reference' => $c->reference, 'issue_date' => now(), 'effective_date' => $c->start_date,
                'expiry_date' => in_array($c->contract_type, ['fixed_term', 'temporary', 'internship'], true) ? $c->end_date : null,
                'department' => $c->employee->department, 'access_level' => 'employee', 'employee_id' => $c->employee_id, 'share_with_employees' => true,
                'contract_id' => $c->id, 'tags' => ['contract', 'executed'], 'uploaded_by_name' => 'ZamPayroll (executed contract)', 'status' => 'active',
            ]);
            CompanyDocumentVersion::create(['document_id' => $doc->id, 'version' => 1, 'path' => $path, 'original_name' => $c->reference . '.pdf',
                'mime' => 'application/pdf', 'extension' => 'pdf', 'size' => strlen($pdf), 'sha256' => $hash, 'note' => 'Executed contract',
                'uploaded_by_name' => 'ZamPayroll']);
        });
        Audit::record('compliance.contract_executed', $c, "{$c->reference} fully executed", ['pdf_sha256' => $hash, 'version' => $c->current_version]);

        $employeeEmail = $c->employee->user?->email ?? $c->employee->portal_email;
        $mail = fn (string $intro, string $url) => [
            'subject' => "Executed contract {$c->reference}", 'eyebrow' => 'Employment contract', 'heading' => 'The contract is fully signed',
            'intro' => $intro, 'rows' => ['Employee' => $c->employee->full_name, 'Reference' => $c->reference, 'Fingerprint (SHA-256)' => substr($hash, 0, 16) . '…'],
            'url' => $url, 'label' => 'View in ZamPayroll', 'attachment' => $pdf, 'attachment_name' => $c->reference . '.pdf',
        ];
        ComplianceMailer::send('contract.executed', [$employeeEmail], $mail('Your employment contract has been signed by everyone. A copy is attached and stays in your portal.', route('portal.documents.index')), 'contract', $c->id);
        $actors = ApprovalRequest::where('approvable_type', 'contract')->where('approvable_id', $c->id)->where('round', $c->round)->whereNotNull('acted_by')->pluck('acted_by');
        $people = User::whereIn('id', $actors->push($c->created_by)->filter())->get();
        ComplianceMailer::send('contract.distributed', $people, $mail('The contract has been signed by the employee and the company. A copy is attached and is filed in the Compliance Centre.', route('compliance.contracts.show', $c)), 'contract', $c->id);
    }

    public static function hrRecipients(EmploymentContract $c)
    {
        $creator = $c->creator ? collect([$c->creator]) : collect();

        return $creator->merge(ComplianceMailer::staffWith('compliance.contracts'))->unique('id')->values();
    }
}
