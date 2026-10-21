<?php

namespace App\Services\Loans;

use App\Mail\LoanApplied;
use App\Mail\LoanDecided;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\TeamScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Loans and salary advances, in three separate, recorded steps:
 *
 *   apply    → the employee (or HR on their behalf) asks
 *   approve  → someone with loans.manage agrees; a ledger entry is created as
 *              "approved — not paid out yet". Nothing is deducted yet.
 *   disburse → someone with loans.disburse records that the money was paid
 *              (date, method, reference). Only now do repayments come out of pay.
 *
 * Cancelling a request or an approved loan that was never paid out is kept on record.
 */
class LoanService
{
    public function apply(Employee $employee, array $data, ?array $documents = null, ?User $by = null): LoanRequest
    {
        $this->checkTerms($data['amount'] ?? 0, $data['payment_plan'] ?? null, $data['installment_amount'] ?? null);

        $req = DB::transaction(function () use ($employee, $data, $documents, $by) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            if (LoanRequest::query()->where('employee_id', $employee->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['amount' => 'There is already a loan request waiting for a decision. Please wait for it first.']);
            }

            return LoanRequest::create([
                'employee_id'        => $employee->id,
                'company_email'      => $data['company_email'] ?? $employee->company_email ?? $employee->user?->email ?? '',
                'amount'             => round((float) $data['amount'], 2),
                'payment_plan'       => $data['payment_plan'],
                'payment_plan_note'  => $data['payment_plan_note'] ?? null,
                'installment_amount' => $data['payment_plan'] === 'once_off' ? null : ($data['installment_amount'] ?? null),
                'reason'             => $data['reason'] ?? null,
                'documents'          => $documents ?: null,
                'status'             => 'pending',
                'recorded_by'        => $by && ! $by->isEmployee() ? $by->id : null,
            ]);
        });

        Audit::record('loan.applied', $req, $employee->full_name . ' asked for a loan of K ' . number_format((float) $req->amount, 2), [], $employee->company_id);
        $this->send(TeamScope::approverEmails($employee, 'loans.manage'), new LoanApplied($req));

        return $req;
    }

    /**
     * Approve a request. HR can adjust the amount, instalment and the month deductions start.
     * Creates the ledger entry in "approved — not paid out yet".
     */
    public function approve(LoanRequest $req, User $by, array $terms = [], ?string $comment = null): Loan
    {
        $loan = DB::transaction(function () use (&$req, $by, $terms, $comment) {
            $req = LoanRequest::query()->whereKey($req->id)->lockForUpdate()->firstOrFail();
            $this->guardDecision($req, $by);

            $amount = round((float) ($terms['amount'] ?? $req->amount), 2);
            $plan = $terms['payment_plan'] ?? $req->payment_plan;
            $installment = $plan === 'once_off' ? null : ($terms['installment_amount'] ?? $req->installment_amount);
            $this->checkTerms($amount, $plan, $installment);
            $first = $this->firstDeduction($terms['first_deduction'] ?? null);

            $req->update(['status' => 'approved', 'hr_comment' => $comment, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);

            $loan = Loan::create([
                'employee_id'         => $req->employee_id,
                'loan_request_id'     => $req->id,
                'principal_amount'    => $amount,
                'balance'             => $amount,
                'payment_plan'        => $plan,
                'payment_plan_note'   => $req->payment_plan_note,
                'installment_amount'  => $installment,
                'start_date'          => $first,
                'next_deduction_date' => $first,
                'status'              => 'pending_disbursement',
                'created_by'          => $by->id,
                'approved_by'         => $by->id,
                'approved_at'         => now(),
            ]);

            $changed = abs($amount - (float) $req->amount) > 0.001 ? ' (asked K ' . number_format((float) $req->amount, 2) . ')' : '';
            Audit::record('loan.approved', $loan, $req->employee->full_name . ': loan of K ' . number_format($amount, 2) . ' approved' . $changed . ', waiting to be paid out', [], $req->company_id);

            return $loan;
        });

        $this->mailEmployee($req->fresh(), new LoanDecided($req->fresh()));

        return $loan;
    }

    public function reject(LoanRequest $req, User $by, ?string $comment = null): LoanRequest
    {
        DB::transaction(function () use (&$req, $by, $comment) {
            $req = LoanRequest::query()->whereKey($req->id)->lockForUpdate()->firstOrFail();
            $this->guardDecision($req, $by);
            $req->update(['status' => 'rejected', 'hr_comment' => $comment, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            Audit::record('loan.rejected', $req, $req->employee->full_name . ': loan request rejected' . ($comment ? " — {$comment}" : ''), [], $req->company_id);
        });
        $this->mailEmployee($req, new LoanDecided($req));

        return $req;
    }

    /** Record that the money was actually paid to the employee. From now on repayments are deducted. */
    public function disburse(Loan $loan, User $by, array $data): Loan
    {
        DB::transaction(function () use (&$loan, $by, $data) {
            $loan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if (! $by->hasPermission('loans.disburse')) {
                throw ValidationException::withMessages(['loan' => 'You don\'t have permission to record loan payouts.']);
            }
            if ($loan->status !== 'pending_disbursement') {
                throw ValidationException::withMessages(['loan' => $loan->isDisbursed() ? 'This loan was already paid out on ' . $loan->disbursed_at->format('j M Y') . '.' : 'Only approved loans can be paid out.']);
            }
            // Two people: whoever approved shouldn't also confirm the payout (administrators excepted).
            if ((int) $loan->approved_by === (int) $by->id && ! $by->isCompanyAdmin()) {
                throw ValidationException::withMessages(['loan' => 'Someone other than the approver needs to record the payout.']);
            }
            if (! isset(Loan::DISBURSEMENT_METHODS[$data['method'] ?? ''])) {
                throw ValidationException::withMessages(['method' => 'Choose how the money was paid.']);
            }
            $date = Carbon::parse($data['date'] ?? today());
            if ($date->isAfter(today())) {
                throw ValidationException::withMessages(['date' => 'Record the payout once it has happened.']);
            }

            // Deductions can't start before the month the money was paid.
            $next = $loan->next_deduction_date && $loan->next_deduction_date->gte($date->copy()->startOfMonth())
                ? $loan->next_deduction_date : $date->copy()->startOfMonth();
            if (! empty($data['first_deduction'])) {
                $next = max($this->firstDeduction($data['first_deduction']), $date->copy()->startOfMonth());
            }

            $loan->forceFill([
                'status' => 'active', 'disbursed_at' => $date->toDateString(), 'disbursed_by' => $by->id,
                'disbursement_method' => $data['method'], 'disbursement_reference' => $data['reference'] ?? null,
                'start_date' => $next, 'next_deduction_date' => $next,
            ])->save();
            Loan::syncEmployeeCache($loan->employee_id);

            Audit::record('loan.disbursed', $loan, $loan->employee->full_name . ': K ' . number_format((float) $loan->principal_amount, 2) . ' paid out by '
                . Loan::DISBURSEMENT_METHODS[$data['method']] . ($loan->disbursement_reference ? ' (ref ' . $loan->disbursement_reference . ')' : '')
                . '. Repayments start ' . Carbon::parse($next)->format('F Y') . '.', [], $loan->company_id);
        });

        return $loan;
    }

    /**
     * Cancel a request (pending: employee or approver) or an approved loan that was never paid out (approver).
     */
    public function cancelRequest(LoanRequest $req, User $by, ?string $reason = null): LoanRequest
    {
        DB::transaction(function () use (&$req, $by, $reason) {
            $req = LoanRequest::query()->whereKey($req->id)->lockForUpdate()->firstOrFail();
            $own = $by->employee_id && (int) $by->employee_id === (int) $req->employee_id;
            $manager = $by->hasPermission('loans.manage') && TeamScope::canSee($by, $req->employee);

            if ($req->status === 'pending' && ($own || $manager)) {
                // fine
            } elseif ($req->status === 'approved' && $manager && ($loan = $req->loan) && $loan->status === 'pending_disbursement') {
                $loan->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason])->save();
            } else {
                throw ValidationException::withMessages(['loan' => $req->status === 'approved'
                    ? 'This loan has already been paid out. Record a repayment or write-off on the loan instead.'
                    : 'You can\'t cancel this request.']);
            }

            $req->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason]);
            Audit::record('loan.cancelled', $req, $req->employee->full_name . ': loan request cancelled' . ($own ? ' by the employee' : '') . ($reason ? " — {$reason}" : ''), [], $req->company_id);
        });

        return $req;
    }

    /** Cancel an approved ledger loan that was never paid out (e.g. one HR entered directly). */
    public function cancelLoan(Loan $loan, User $by, ?string $reason = null): Loan
    {
        DB::transaction(function () use (&$loan, $by, $reason) {
            $loan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($loan->status !== 'pending_disbursement' || ! $by->hasPermission('loans.manage')) {
                throw ValidationException::withMessages(['loan' => 'Only loans that haven\'t been paid out can be cancelled.']);
            }
            $loan->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason])->save();
            if ($loan->loanRequest && $loan->loanRequest->status === 'approved') {
                $loan->loanRequest->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason]);
            }
            Audit::record('loan.cancelled', $loan, $loan->employee->full_name . ': approved loan cancelled before payout' . ($reason ? " — {$reason}" : ''), [], $loan->company_id);
        });

        return $loan;
    }

    public function canDecide(User $user, LoanRequest $req): bool
    {
        return $req->status === 'pending' && $user->hasPermission('loans.manage')
            && ! ($user->employee_id && (int) $user->employee_id === (int) $req->employee_id && ! $user->isCompanyAdmin())
            && TeamScope::canSee($user, $req->employee);
    }

    public function canDisburse(User $user, Loan $loan): bool
    {
        return $loan->status === 'pending_disbursement' && $user->hasPermission('loans.disburse')
            && ((int) $loan->approved_by !== (int) $user->id || $user->isCompanyAdmin());
    }

    /* ------------------------------------------------------------------ */

    private function guardDecision(LoanRequest $req, User $by): void
    {
        if (! $this->canDecide($by, $req)) {
            throw ValidationException::withMessages(['loan' => $req->status === 'pending' ? 'You can\'t decide on this request.' : 'This request was already ' . strtolower($req->statusLabel()) . '.']);
        }
    }

    private function checkTerms($amount, ?string $plan, $installment): void
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the loan amount.']);
        }
        if (! isset(LoanRequest::PAYMENT_PLANS[$plan ?? ''])) {
            throw ValidationException::withMessages(['payment_plan' => 'Choose how it will be repaid.']);
        }
        if ($plan !== 'once_off') {
            if ((float) $installment <= 0) {
                throw ValidationException::withMessages(['installment_amount' => 'Enter how much is deducted each time.']);
            }
            if ((float) $installment > $amount) {
                throw ValidationException::withMessages(['installment_amount' => 'The instalment can\'t be more than the loan.']);
            }
        }
    }

    /** First deduction month from "YYYY-MM" (default: next month). */
    private function firstDeduction(?string $ym): Carbon
    {
        if ($ym && preg_match('/^\d{4}-\d{2}/', $ym)) {
            $d = Carbon::parse(substr($ym, 0, 7) . '-01');
            if ($d->lt(now()->startOfMonth())) {
                throw ValidationException::withMessages(['first_deduction' => 'Deductions can\'t start in a month that has passed.']);
            }

            return $d;
        }

        return now()->addMonthNoOverflow()->startOfMonth();
    }

    private function send(array $to, $mailable): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function mailEmployee(LoanRequest $req, $mailable): void
    {
        $email = $req->employee?->user?->email ?: $req->employee?->company_email ?: $req->employee?->personal_email ?: $req->company_email;
        if ($email) {
            $this->send([$email], $mailable);
        }
    }
}
