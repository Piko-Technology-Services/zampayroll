<?php

namespace App\Services;

use App\Mail\PayrollApprovalMail;
use App\Models\CompanyRole;
use App\Models\Payroll;
use App\Models\PayrollApprovalStep;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollRunEvent;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Draft → submitted → each approval step in order → approved & locked → paid.
 * The chain is set by the company admin (Settings → Payroll approvals) and is
 * frozen onto the run when it is submitted, so later edits don't change a run
 * that's already moving.
 */
class PayrollApprovals
{
    /** Default when the admin hasn't set a chain yet: one sign-off by an administrator. */
    public const DEFAULT_CHAIN = [
        ['position' => 1, 'name' => 'Approval', 'role_key' => 'company_admin', 'user_id' => null],
    ];

    /** The company's current chain, as plain arrays. */
    public function chain(): array
    {
        $steps = PayrollApprovalStep::query()->orderBy('position')->get();

        if ($steps->isEmpty()) {
            return self::DEFAULT_CHAIN;
        }

        return $steps->values()->map(fn ($s, $i) => [
            'position' => $i + 1,
            'name'     => $s->name,
            'role_key' => $s->role_key,
            'user_id'  => $s->user_id,
        ])->all();
    }

    /** The chain a run is following (frozen at submission), or the live one. */
    public function chainFor(PayrollRun $run): array
    {
        return $run->approval_chain ?: $this->chain();
    }

    public function currentStep(PayrollRun $run): ?array
    {
        if (! $run->isInReview() || ! $run->current_step) {
            return null;
        }

        return $this->chainFor($run)[$run->current_step - 1] ?? null;
    }

    /** Staff who can act on a step. */
    public function approversFor(array $step): Collection
    {
        $q = User::query()->where('company_id', Tenancy::companyId());

        if (! empty($step['user_id'])) {
            $q->where('id', $step['user_id']);
        } elseif (! empty($step['role_key'])) {
            $legacy = array_keys(array_filter(\App\Support\Permissions::LEGACY_ROLE_MAP, fn ($v) => $v === $step['role_key']));
            $q->whereIn('role', array_merge([$step['role_key']], $legacy));
        } else {
            return collect();
        }

        return $q->get()->filter(fn (User $u) => $u->isStaff())->values();
    }

    /** Human description of who approves a step: "Payroll Officer" / "Jane Banda". */
    public function stepWho(array $step): string
    {
        if (! empty($step['user_id'])) {
            return User::find($step['user_id'])?->name ?? 'a removed user';
        }

        return CompanyRole::query()->where('key', $step['role_key'])->value('name')
            ?? ucfirst(str_replace('_', ' ', (string) $step['role_key']));
    }

    /** Null when the user may approve the current step, otherwise the reason they can't. */
    public function whyCantApprove(?User $user, PayrollRun $run): ?string
    {
        $step = $this->currentStep($run);

        if (! $user || ! $step) {
            return 'This run isn\'t waiting for approval.';
        }

        if (! $this->approversFor($step)->contains('id', $user->id)) {
            return 'This step is for ' . $this->stepWho($step) . '.';
        }

        $company = Tenancy::company();
        if ((int) $run->submitted_by === (int) $user->id && $company && ! $company->payroll_allow_self_approval) {
            return 'You prepared this payroll, so someone else has to approve it.';
        }

        // The same person shouldn't approve two steps of the same run.
        $already = $run->events()->where('action', 'approved')->where('user_id', $user->id)->exists();
        if ($already && count($this->chainFor($run)) > 1 && $this->approversFor($step)->count() > 1) {
            return 'You already approved an earlier step — another approver should sign this one.';
        }

        return null;
    }

    public function canApprove(?User $user, PayrollRun $run): bool
    {
        return $this->whyCantApprove($user, $run) === null;
    }

    /* ------------------------------------------------------------------ */

    public function submit(PayrollRun $run, User $by, ?string $comment = null): void
    {
        if (! $run->isEditable()) {
            throw new \DomainException('This payroll has already been submitted.');
        }
        if (! $run->payrolls()->exists()) {
            throw new \DomainException('Calculate the payroll before submitting it.');
        }

        $chain = $this->chain();

        $run->update([
            'status'         => PayrollRun::IN_REVIEW,
            'current_step'   => 1,
            'submitted_at'   => now(),
            'submitted_by'   => $by->id,
            'approval_chain' => $chain,
        ] + $this->totals($run, true));

        $this->log($run, 'submitted', $by, $comment);
        $this->notifyStep($run->fresh());
    }

    public function approve(PayrollRun $run, User $by, ?string $comment = null): void
    {
        if ($why = $this->whyCantApprove($by, $run)) {
            throw new \DomainException($why);
        }

        $step = $this->currentStep($run);
        $chain = $this->chainFor($run);
        $this->log($run, 'approved', $by, $comment, $step);

        if ($run->current_step < count($chain)) {
            $run->update(['current_step' => $run->current_step + 1]);
            $this->notifyStep($run->fresh());

            return;
        }

        $this->lock($run, $by);
    }

    public function sendBack(PayrollRun $run, User $by, string $comment): void
    {
        if ($why = $this->whyCantApprove($by, $run)) {
            throw new \DomainException($why);
        }

        $step = $this->currentStep($run);
        $run->update(['status' => PayrollRun::CALCULATED, 'current_step' => null]);
        $this->log($run, 'sent_back', $by, $comment, $step);

        $this->mail($run->submitter, $run, 'sent_back', $by, $comment);
    }

    /** The preparer (or anyone who can prepare payroll) pulls it back to change something. */
    public function recall(PayrollRun $run, User $by, ?string $comment = null): void
    {
        if (! $run->isInReview()) {
            throw new \DomainException('Only a run waiting for approval can be recalled.');
        }

        $run->update(['status' => PayrollRun::CALCULATED, 'current_step' => null]);
        $this->log($run, 'recalled', $by, $comment);
    }

    public function markPaid(PayrollRun $run, User $by, ?string $comment = null): void
    {
        if ($run->status !== PayrollRun::APPROVED) {
            throw new \DomainException('Only an approved payroll can be marked as paid.');
        }

        DB::transaction(function () use ($run, $by, $comment) {
            Payroll::where('payroll_run_id', $run->id)->update(['status' => 'Paid']);
            $run->update(['status' => PayrollRun::PAID, 'paid_at' => now(), 'paid_by' => $by->id]);
            $this->log($run, 'paid', $by, $comment);
        });
    }

    /** Final approval: payslips are locked and loan repayments are posted to the ledger. */
    protected function lock(PayrollRun $run, User $by): void
    {
        DB::transaction(function () use ($run, $by) {
            Payroll::where('payroll_run_id', $run->id)->update(['status' => 'Approved']);

            $loans = app(LoanRepaymentService::class);
            foreach ($run->payrolls()->get() as $payroll) {
                $loans->recordDeductions($payroll);
            }

            $run->update([
                'status'       => PayrollRun::APPROVED,
                'current_step' => null,
                'finalized_at' => now(),
                'finalized_by' => $by->id,
            ] + $this->totals($run, true));

            $this->log($run, 'locked', $by);
        });

        $this->mail($run->submitter, $run, 'locked', $by);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Run totals. With $forUpdate the array only holds payroll_runs columns.
     */
    public function totals(PayrollRun $run, bool $forUpdate = false): array
    {
        $base = [
            'total_income'     => (float) $run->payrolls()->sum('total_income'),
            'total_deductions' => (float) $run->payrolls()->sum('total_deductions'),
            'net_pay'          => (float) $run->payrolls()->sum('net_pay'),
        ];

        if ($forUpdate) {
            return $base;
        }

        $codes = PayrollItem::query()
            ->whereIn('payroll_id', $run->payrolls()->select('id'))
            ->whereIn('code', ['D00', 'D01', 'D02'])
            ->selectRaw('code, SUM(amount) as total')->groupBy('code')->pluck('total', 'code');

        return $base + [
            'people' => $run->payrolls()->count(),
            'paye'   => (float) ($codes['D00'] ?? 0),
            'napsa'  => (float) ($codes['D01'] ?? 0),
            'nhima'  => (float) ($codes['D02'] ?? 0),
        ];
    }

    public function log(PayrollRun $run, string $action, ?User $by, ?string $comment = null, ?array $step = null): PayrollRunEvent
    {
        return PayrollRunEvent::create([
            'payroll_run_id' => $run->id,
            'action'         => $action,
            'step_position'  => $step['position'] ?? null,
            'step_name'      => $step['name'] ?? null,
            'user_id'        => $by?->id,
            'comment'        => $comment ?: null,
            'snapshot'       => $this->totals($run),
        ]);
    }

    protected function notifyStep(PayrollRun $run): void
    {
        if (! $step = $this->currentStep($run)) {
            return;
        }

        foreach ($this->approversFor($step) as $user) {
            if ((int) $user->id !== (int) $run->submitted_by || $this->approversFor($step)->count() === 1) {
                $this->mail($user, $run, 'review', null, null, $step);
            }
        }
    }

    protected function mail(?User $to, PayrollRun $run, string $kind, ?User $by = null, ?string $comment = null, ?array $step = null): void
    {
        if (! $to?->email) {
            return;
        }

        try {
            Mail::to($to->email)->send(new PayrollApprovalMail($run, $kind, $to, $by, $comment, $step));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
