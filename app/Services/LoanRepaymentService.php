<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Payroll;
use App\Models\PayrollRule;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoanRepaymentService
{
    /**
     * Call this for an employee BEFORE PayrollEngine::build() — it creates
     * (or refreshes) a Loan Repayment deduction adjustment for any active
     * loan due this period, so the engine picks it up when it reads
     * adjustments. Idempotent: safe to call again if a run is regenerated,
     * it clears and rewrites only the loan-linked adjustments it owns.
     */
    public function syncForEmployee(PayrollRun $run, int $employeeId): void
    {
        $rule = PayrollRule::where('code', 'D_LOAN')->first();

        if (! $rule) {
            Log::warning('LoanRepaymentService: D_LOAN payroll rule is missing — run the seed migration. Skipping loan deductions.');
            return;
        }

        $periodDate = $this->resolvePeriodDate($run);

        // Older versions lost the loan link (loan_id wasn't fillable), leaving
        // orphan "Loan Repayment" lines that piled up on every recalculation.
        PayrollRunAdjustment::where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->where('payroll_rule_id', $rule->id)
            ->whereNull('loan_id')
            ->where('name', 'like', 'Loan Repayment%')
            ->delete();

        // Lines for loans that are no longer deductible (paused, cancelled, not paid out) come off this run.
        PayrollRunAdjustment::where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->whereNotNull('loan_id')
            ->whereNotIn('loan_id', Loan::where('employee_id', $employeeId)->where('status', 'active')->whereNotNull('disbursed_at')->select('id'))
            ->delete();

        // Only loans that were actually paid out (disbursed) and are running are repaid from pay.
        $loans = Loan::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereNotNull('disbursed_at')
            ->get();

        foreach ($loans as $loan) {
            // Clear any adjustment this service previously created for this
            // loan/run before deciding again — keeps re-generation clean.
            PayrollRunAdjustment::where('payroll_run_id', $run->id)
                ->where('employee_id', $employeeId)
                ->where('loan_id', $loan->id)
                ->delete();

            if (! $this->isDue($loan, $periodDate) || $this->alreadyDeductedThisPeriod($loan, $run)) {
                continue;
            }

            $amount = $this->deductionAmount($loan);

            if ($amount <= 0) {
                continue;
            }

            PayrollRunAdjustment::create([
                'payroll_run_id'  => $run->id,
                'employee_id'     => $employeeId,
                'payroll_rule_id' => $rule->id,
                'loan_id'         => $loan->id,
                'name'            => 'Loan Repayment' . ($loan->payment_plan_note ? " — {$loan->payment_plan_note}" : ''),
                'type'            => 'deduction',
                'formula_type'    => 'fixed',
                'value'           => $amount,
                'tax_profile'     => null,
                'active'          => true,
            ]);
        }
    }

    /**
     * Call this AFTER PayrollEngine::build() has written the payslip items
     * for this payroll. Confirms the loan deduction actually landed on the
     * payslip, then records it against the loan ledger and advances the
     * loan's balance / next due date. Safe to call again on the same
     * payroll — it will not double-record.
     */
    public function recordDeductions(Payroll $payroll): void
    {
        $adjustments = PayrollRunAdjustment::where('payroll_run_id', $payroll->payroll_run_id)
            ->where('employee_id', $payroll->employee_id)
            ->whereNotNull('loan_id')
            ->get();

        if ($adjustments->isEmpty()) {
            return;
        }

        foreach ($adjustments as $adj) {
            $loan = Loan::find($adj->loan_id);

            if (! $loan) {
                continue;
            }

            $alreadyRecorded = LoanPayment::where('loan_id', $loan->id)
                ->where('payroll_id', $payroll->id)
                ->exists();

            // Never take a second repayment for the same month (e.g. two payroll runs for one period).
            $run = PayrollRun::find($payroll->payroll_run_id);
            if ($alreadyRecorded || ! $loan->isDisbursed() || ($run && $this->alreadyDeductedThisPeriod($loan, $run, false))) {
                continue;
            }

            $amount     = (float) $adj->value;
            $newBalance = round(max(0, (float) $loan->balance - $amount), 2);

            LoanPayment::create([
                'loan_id'       => $loan->id,
                'payroll_id'    => $payroll->id,
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'type'          => 'deduction',
            ]);

            $loan->balance = $newBalance;

            if ($newBalance <= 0) {
                $loan->status = 'completed';
            } else {
                $loan->next_deduction_date = $this->advanceDate($loan);
            }

            $loan->save();

            Log::info('LoanRepaymentService: deduction recorded', [
                'loan_id'     => $loan->id,
                'employee_id' => $payroll->employee_id,
                'payroll_id'  => $payroll->id,
                'amount'      => $amount,
                'new_balance' => $newBalance,
            ]);
        }

        Loan::syncEmployeeCache($payroll->employee_id);
    }

    /**
     * Has this loan already been repaid from (or claimed by) another payroll for the same month?
     * Another run that is still being prepared counts too, so two drafts can't both deduct.
     */
    public function alreadyDeductedThisPeriod(Loan $loan, PayrollRun $run, bool $countDrafts = true): bool
    {
        $sameMonth = PayrollRun::query()->where('id', '!=', $run->id)->where('period', $run->period)->pluck('id');
        if ($sameMonth->isEmpty()) {
            return false;
        }

        return LoanPayment::where('loan_id', $loan->id)->where('type', 'deduction')
                ->whereIn('payroll_id', Payroll::whereIn('payroll_run_id', $sameMonth)->select('id'))->exists()
            || ($countDrafts && PayrollRunAdjustment::where('loan_id', $loan->id)->whereIn('payroll_run_id', $sameMonth)->exists());
    }

    private function isDue(Loan $loan, Carbon $periodDate): bool
    {
        if ($loan->payment_plan === 'once_off') {
            return true; // deducted in full the next run it's active for
        }

        if (! $loan->next_deduction_date) {
            return true;
        }

        return Carbon::parse($loan->next_deduction_date)->lte($periodDate);
    }

    private function deductionAmount(Loan $loan): float
    {
        if ($loan->payment_plan === 'once_off') {
            return (float) $loan->balance; // full remaining balance in one shot
        }

        $installment = (float) ($loan->installment_amount ?? 0);

        // Never deduct more than what's actually left owing.
        return min($installment, (float) $loan->balance);
    }

    private function advanceDate(Loan $loan): string
    {
        $base = Carbon::parse($loan->next_deduction_date ?? now());

        return match ($loan->payment_plan) {
            'bi_monthly' => $base->addMonths(2)->toDateString(),
            default      => $base->addMonth()->toDateString(), // monthly, other
        };
    }

    /**
     * PayrollRun->period is a free-text string in this app (e.g. "August
     * 2026") rather than a strict date column, so this parses leniently and
     * falls back to "now" if Carbon can't understand it. Worth confirming
     * this matches how your period strings actually look — if they're a
     * fixed format like "YYYY-MM", swap in Carbon::createFromFormat for a
     * guaranteed-correct parse instead of the loose Carbon::parse guess.
     */
    private function resolvePeriodDate(PayrollRun $run): Carbon
    {
        // A repayment due any day in the run's month belongs to that month's payroll.
        if ($run->period_date) {
            return $run->period_date->copy()->endOfMonth();
        }

        try {
            return Carbon::parse($run->period);
        } catch (\Exception $e) {
            return now();
        }
    }
}
