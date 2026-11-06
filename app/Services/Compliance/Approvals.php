<?php

namespace App\Services\Compliance;

use App\Models\Compliance\ApprovalRequest;
use App\Models\Compliance\ApprovalWorkflow;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The approval engine shared by contracts and policies. Each company designs its own sequential chain
 * (Settings › Approval workflows). Steps go to a named person or to anyone holding a role; a step can only be
 * done by someone else when the company configured that person as the step's delegate.
 */
class Approvals
{
    /** The workflow that applies: one limited to this contract type wins over a general one. */
    public static function workflowFor(string $appliesTo, ?string $contractType = null): ?ApprovalWorkflow
    {
        $flows = ApprovalWorkflow::with('steps')->where('applies_to', $appliesTo)->where('is_active', true)->orderBy('id')->get()
            ->filter(fn ($w) => $w->steps->isNotEmpty() && $w->appliesToType($contractType));

        return $flows->first(fn ($w) => ! empty($w->contract_types)) ?? $flows->first();
    }

    /** Create this round's steps; the first becomes pending. Returns the first step. */
    public static function start(string $type, int $subjectId, ApprovalWorkflow $flow, int $version, int $round): ?ApprovalRequest
    {
        $first = null;
        foreach ($flow->steps as $i => $step) {
            $req = ApprovalRequest::create([
                'approvable_type' => $type, 'approvable_id' => $subjectId, 'version' => $version, 'round' => $round,
                'position' => $step->position, 'label' => $step->label, 'approver_type' => $step->approver_type,
                'approver_user_id' => $step->approver_user_id, 'approver_role_key' => $step->approver_role_key,
                'delegate_user_id' => $step->delegate_user_id, 'action' => $step->action, 'status' => $i === 0 ? 'pending' : 'waiting',
            ]);
            $first ??= $req;
        }

        return $first;
    }

    /** Pending steps this user may act on. */
    public static function pendingQueryFor(User $user): Builder
    {
        return ApprovalRequest::query()->where('status', 'pending')->where(function (Builder $w) use ($user) {
            $w->where(fn ($q) => $q->where('approver_type', 'user')->where('approver_user_id', $user->id))
                ->orWhere(fn ($q) => $q->where('approver_type', 'role')->where('approver_role_key', $user->roleKey()))
                ->orWhere('delegate_user_id', $user->id);
        });
    }

    public static function pendingCountFor(?User $user): int
    {
        if (! $user || ! $user->isStaff() || ! Tenancy::companyId()) {
            return 0;
        }
        try {
            return self::pendingQueryFor($user)->count();
        } catch (\Throwable $e) {
            return 0; // tables not migrated yet
        }
    }

    /** People who should hear that a step is waiting for them. */
    public static function recipients(ApprovalRequest $req): Collection
    {
        $users = $req->approver_type === 'user'
            ? User::where('company_id', $req->company_id)->whereKey($req->approver_user_id)->get()
            : User::where('company_id', $req->company_id)->get()->filter(fn (User $u) => $u->isStaff() && $u->roleKey() === $req->approver_role_key);
        if ($req->delegate_user_id) {
            $users = $users->merge(User::where('company_id', $req->company_id)->whereKey($req->delegate_user_id)->get());
        }

        return $users->unique('id')->values();
    }

    /**
     * Record a decision. Returns 'next' (another step is now pending), 'complete' (the chain finished)
     * or 'returned' (rejected or changes requested; later steps cancelled).
     */
    public static function decide(ApprovalRequest $req, User $user, string $decision, ?string $comment): array
    {
        abort_unless($req->canBeActedOnBy($user), 403, 'This step is not waiting for you.');
        abort_unless(in_array($decision, ['approve', 'reject', 'changes'], true), 422);

        $req->update([
            'status' => ['approve' => 'approved', 'reject' => 'rejected', 'changes' => 'changes'][$decision],
            'acted_by' => $user->id, 'acted_by_name' => $user->name, 'acted_as_delegate' => ! $req->isAssignedTo($user) && $req->isDelegate($user),
            'acted_at' => now(), 'comment' => $comment,
        ]);
        Audit::record('compliance.approval_' . $decision, $req, "{$req->label}: {$decision} by {$user->name}" . ($comment ? " ({$comment})" : ''),
            ['subject' => $req->approvable_type . ':' . $req->approvable_id, 'version' => $req->version, 'round' => $req->round]);

        $siblings = ApprovalRequest::where('approvable_type', $req->approvable_type)->where('approvable_id', $req->approvable_id)
            ->where('round', $req->round)->orderBy('position');

        if ($decision !== 'approve') {
            (clone $siblings)->whereIn('status', ['waiting', 'pending'])->update(['status' => 'cancelled', 'updated_at' => now()]);

            return ['outcome' => 'returned', 'next' => null];
        }

        $next = (clone $siblings)->where('status', 'waiting')->first();
        if ($next) {
            $next->update(['status' => 'pending']);

            return ['outcome' => 'next', 'next' => $next];
        }

        return ['outcome' => 'complete', 'next' => null];
    }

    /** Stop any open steps (the document was withdrawn or edited). */
    public static function cancelOpen(string $type, int $id): void
    {
        ApprovalRequest::where('approvable_type', $type)->where('approvable_id', $id)->whereIn('status', ['waiting', 'pending'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }
}
