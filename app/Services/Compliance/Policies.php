<?php

namespace App\Services\Compliance;

use App\Models\Compliance\ApprovalRequest;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\ComplianceSetting;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\Letterhead;
use App\Models\Compliance\PolicyAcknowledgement;
use App\Models\Compliance\PolicyVersion;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Company policies: draft → (approval) → active → employees acknowledge. A revision is a new version that goes
 * through approval while the previous version stays active; previous versions are kept.
 */
class Policies
{
    public static function nextReference(): string
    {
        $prefix = 'POL-';
        $last = CompanyPolicy::withTrashed()->where('reference', 'like', $prefix . '%')->orderByDesc('id')->value('reference');

        return $prefix . str_pad((string) (($last ? (int) substr($last, 4) : 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function create(DocumentTemplate $template, ?Letterhead $lh, User $user, ?string $title = null): CompanyPolicy
    {
        abort_unless($template->kind === 'policy', 422);

        return DB::transaction(function () use ($template, $lh, $user, $title) {
            $p = CompanyPolicy::create([
                'template_id' => $template->id, 'letterhead_id' => $lh?->id, 'reference' => self::nextReference(), 'title' => $title ?: $template->name,
                'category' => $template->key, 'legal_status' => $template->legal_status, 'status' => 'draft', 'current_version' => 1,
                'requires_acknowledgement' => true, 'review_date' => now()->addYear(), 'created_by' => $user->id,
            ]);
            PolicyVersion::create(['policy_id' => $p->id, 'version' => 1, 'body' => HtmlSanitizer::clean($template->body), 'hash' => '',
                'status' => 'draft', 'change_reason' => 'Created from ' . $template->name, 'created_by' => $user->id]);
            Audit::record('compliance.policy_created', $p, "{$p->reference} {$p->title} created");

            return $p;
        });
    }

    public static function values(CompanyPolicy $p): array
    {
        $values = Placeholders::company(Tenancy::company(), ComplianceSetting::current());
        if ($p->published_at) {
            $values['effective_date'] = $p->published_at->format('j F Y');
        }

        return $values;
    }

    public static function draft(CompanyPolicy $p): PolicyVersion
    {
        return PolicyVersion::where('policy_id', $p->id)->where('version', $p->current_version)->firstOrFail();
    }

    public static function renderedBody(CompanyPolicy $p, ?int $version = null, bool $highlight = false): string
    {
        $v = PolicyVersion::where('policy_id', $p->id)->where('version', $version ?? $p->current_version)->firstOrFail();

        return $v->hash !== '' ? $v->body : Placeholders::fill($v->body, self::values($p), $highlight);
    }

    public static function isEditable(CompanyPolicy $p): bool
    {
        return in_array($p->status, ['draft', 'changes'], true);
    }

    public static function saveDraft(CompanyPolicy $p, string $body, array $attrs, User $user, ?string $reason = null): void
    {
        abort_unless(self::isEditable($p), 422, 'Start a revision to change an active policy.');
        DB::transaction(function () use ($p, $body, $attrs, $user, $reason) {
            $v = self::draft($p);
            if ($v->hash !== '') {
                // Sent back by an approver: keep what they saw, continue on a fresh version.
                $p->current_version++;
                PolicyVersion::create(['policy_id' => $p->id, 'version' => $p->current_version, 'body' => HtmlSanitizer::clean($body), 'hash' => '',
                    'status' => 'draft', 'change_reason' => $reason ?: $v->change_reason, 'created_by' => $user->id]);
            } else {
                $v->update(['body' => HtmlSanitizer::clean($body), 'change_reason' => $reason ?: $v->change_reason]);
            }
            $p->fill($attrs)->save();
        });
        Audit::record('compliance.policy_edited', $p, "{$p->reference} draft v{$p->current_version} edited", ['reason' => $reason]);
    }

    /** Start a new version of an active policy. */
    public static function revise(CompanyPolicy $p, User $user, string $reason): void
    {
        abort_unless($p->status === 'active' && ! $p->hasPendingRevision(), 422);
        DB::transaction(function () use ($p, $user, $reason) {
            $active = $p->activeVersionRow();
            $p->current_version++;
            PolicyVersion::create(['policy_id' => $p->id, 'version' => $p->current_version, 'body' => $active->body, 'hash' => '',
                'status' => 'draft', 'change_reason' => $reason, 'created_by' => $user->id]);
            $p->update(['status' => 'draft', 'current_version' => $p->current_version]);
        });
        Audit::record('compliance.policy_revision_started', $p, "{$p->reference} revision v{$p->current_version} started", ['reason' => $reason]);
    }

    /** Freeze the draft, then route it for approval, or publish straight away when the company has no policy workflow. */
    public static function submit(CompanyPolicy $p, User $user, bool $freshAck = true): string
    {
        abort_unless(self::isEditable($p), 422);
        $v = self::draft($p);
        if ($p->active_version && trim((string) $v->change_reason) === '') {
            throw ValidationException::withMessages(['change_reason' => 'Say why the policy is being updated.']);
        }

        DB::transaction(function () use ($p, $v, $freshAck) {
            $body = Placeholders::fill($v->body, self::values($p));
            $v->update(['body' => $body, 'hash' => hash('sha256', $body), 'requires_acknowledgement' => $freshAck || ! $p->active_version]);
        });

        $flow = Approvals::workflowFor('policy');
        if (! $flow) {
            self::publish($p->fresh(), $user);

            return 'published';
        }

        DB::transaction(function () use ($p, $flow) {
            $p->update(['status' => 'in_approval', 'round' => $p->round + 1]);
            $first = Approvals::start('policy', $p->id, $flow, $p->current_version, $p->round);
            self::notifyStep($p, $first);
        });
        Audit::record('compliance.policy_submitted', $p, "{$p->reference} v{$p->current_version} submitted for approval");

        return 'submitted';
    }

    public static function decide(CompanyPolicy $p, ApprovalRequest $req, User $user, string $decision, ?string $comment): string
    {
        abort_unless($p->status === 'in_approval' && $req->approvable_type === 'policy' && (int) $req->approvable_id === (int) $p->id, 422);
        if ($decision !== 'approve' && trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => 'Please give a reason.']);
        }
        $r = Approvals::decide($req, $user, $decision, $comment);
        if ($r['outcome'] === 'next') {
            self::notifyStep($p, $r['next']);
        } elseif ($r['outcome'] === 'complete') {
            self::publish($p->fresh(), $user);
        } else {
            $p->update(['status' => 'changes']);
            ComplianceMailer::send('policy.changes', ComplianceMailer::staffWith('compliance.policies'), [
                'subject' => 'Changes requested: ' . $p->title, 'eyebrow' => 'Company policy', 'heading' => 'An approver sent the policy back',
                'intro' => "{$user->name} ({$req->label}): {$comment}", 'url' => route('compliance.policies.show', $p), 'label' => 'Open policy',
            ], 'policy', $p->id);
        }

        return $r['outcome'];
    }

    public static function notifyStep(CompanyPolicy $p, ?ApprovalRequest $req): void
    {
        if (! $req) {
            return;
        }
        ComplianceMailer::send('policy.awaiting_approval', Approvals::recipients($req), [
            'subject' => 'Policy awaiting your approval: ' . $p->title, 'eyebrow' => 'Approval needed', 'heading' => 'A policy is waiting for you',
            'intro' => "Step: {$req->label}.", 'rows' => ['Policy' => $p->title, 'Version' => (string) $p->current_version],
            'url' => route('compliance.approvals.show', $req), 'label' => 'Review now',
        ], 'policy', $p->id);
    }

    public static function publish(CompanyPolicy $p, User $user): void
    {
        DB::transaction(function () use ($p) {
            PolicyVersion::where('policy_id', $p->id)->where('status', 'published')->update(['status' => 'superseded']);
            PolicyVersion::where('policy_id', $p->id)->where('version', $p->current_version)->update(['status' => 'published', 'published_at' => now()]);
            $p->update(['status' => 'active', 'active_version' => $p->current_version, 'published_at' => now()]);
        });
        Audit::record('compliance.policy_published', $p, "{$p->reference} v{$p->current_version} published");

        $v = $p->activeVersionRow();
        if ($p->requires_acknowledgement && $v->requires_acknowledgement) {
            $emails = Employee::workforce()->get()->filter->hasPortalAccess()->map(fn ($e) => $e->user?->email ?? $e->portal_email);
            ComplianceMailer::send($p->active_version > 1 ? 'policy.updated' : 'policy.published', $emails, [
                'subject' => ($p->active_version > 1 ? 'Updated policy: ' : 'New policy: ') . $p->title,
                'eyebrow' => 'Company policies & notices', 'heading' => ($p->active_version > 1 ? 'A policy was updated' : 'A new company policy') . ': please read and acknowledge',
                'intro' => Tenancy::company()->name . " has published \"{$p->title}\". Sign in to your portal to read it and confirm you have received it.",
                'url' => route('portal.documents.policy', $p), 'label' => 'Read the policy',
            ], 'policy', $p->id);
        }
    }

    public static function acknowledge(CompanyPolicy $p, User $user, int $version, string $hash): PolicyAcknowledgement
    {
        $employee = $user->employee;
        abort_unless($employee && $p->status !== 'archived' && $p->active_version && (int) $p->active_version === $version, 422);
        $v = $p->activeVersionRow();
        if (! hash_equals($v->hash, $hash)) {
            throw ValidationException::withMessages(['acknowledge' => 'The policy was updated while you were reading it. Please reload and read the latest version.']);
        }
        $ack = PolicyAcknowledgement::firstOrCreate(['policy_id' => $p->id, 'version' => $version, 'employee_id' => $employee->id], [
            'user_id' => $user->id, 'document_hash' => $v->hash, 'statement' => PolicyAcknowledgement::STATEMENT,
            'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255), 'acknowledged_at' => now(),
        ]);
        if ($ack->wasRecentlyCreated) {
            Audit::record('compliance.policy_acknowledged', $p, "{$employee->full_name} acknowledged {$p->reference} v{$version}");
        }

        return $ack;
    }

    /** The version an employee must acknowledge: the newest published one that asked for acknowledgement. */
    public static function ackVersion(CompanyPolicy $p): ?int
    {
        if (! $p->requires_acknowledgement || ! $p->active_version) {
            return null;
        }

        return PolicyVersion::where('policy_id', $p->id)->where('version', '<=', $p->active_version)->whereIn('status', ['published', 'superseded'])
            ->where('requires_acknowledgement', true)->max('version');
    }

    /** Employees (with a portal) who have not acknowledged the version that needs it. */
    public static function outstanding(CompanyPolicy $p)
    {
        $ver = self::ackVersion($p);
        if (! $ver) {
            return collect();
        }
        $done = PolicyAcknowledgement::where('policy_id', $p->id)->where('version', '>=', $ver)->pluck('employee_id')->all();

        return Employee::workforce()->whereNotIn('id', $done)->get();
    }
}
