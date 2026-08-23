<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

/**
 * Amortization helpers shared by Loan (an actual ledger entry, schedules
 * forward from its remaining `balance`) and LoanRequest (a proposed loan,
 * schedules forward from the requested `amount` as a preview before it's
 * ever added to the ledger). Both expose: payment_plan, installment_amount,
 * and either `balance` (Loan) or `amount` (LoanRequest).
 */
trait HasInstallmentSchedule
{
    /**
     * How many deductions are needed to fully repay, given the current
     * remaining amount and installment size. Null if it can't be computed
     * (no installment amount set yet, or nothing owing).
     */
    public function estimatedInstallments(): ?int
    {
        if (($this->payment_plan ?? null) === 'once_off') {
            return $this->scheduleRemaining() > 0 ? 1 : 0;
        }

        $installment = (float) ($this->installment_amount ?? 0);
        $remaining   = $this->scheduleRemaining();

        if ($installment <= 0 || $remaining <= 0) {
            return null;
        }

        return (int) ceil($remaining / $installment);
    }

    /**
     * Projected date of the final deduction, based on the schedule's
     * cadence (monthly / every two months). Null if it can't be computed.
     */
    public function estimatedPayoffDate(): ?Carbon
    {
        $installments = $this->estimatedInstallments();

        if (! $installments) {
            return null;
        }

        $interval = ($this->payment_plan ?? null) === 'bi_monthly' ? 2 : 1;

        return $this->scheduleStartDate()->copy()->addMonths($installments * $interval);
    }

    /**
     * Month-by-month deduction preview. Each row: installment number,
     * date, amount, and remaining balance after that installment.
     * Capped at $limit rows as a safety net against runaway loops if
     * installment_amount is tiny relative to the balance.
     *
     * @return array<int, array{no:int, date:Carbon, amount:float, balance_after:float}>
     */
    public function installmentSchedule(int $limit = 36): array
    {
        $remaining = $this->scheduleRemaining();

        if ($remaining <= 0) {
            return [];
        }

        if (($this->payment_plan ?? null) === 'once_off') {
            return [[
                'no'            => 1,
                'date'          => $this->scheduleStartDate(),
                'amount'        => round($remaining, 2),
                'balance_after' => 0.0,
            ]];
        }

        $installment = (float) ($this->installment_amount ?? 0);

        if ($installment <= 0) {
            return [];
        }

        $interval = ($this->payment_plan ?? null) === 'bi_monthly' ? 2 : 1;

        $rows    = [];
        $balance = $remaining;
        $date    = $this->scheduleStartDate();
        $no      = 1;

        while ($balance > 0 && $no <= $limit) {
            $amount  = min($installment, $balance);
            $balance = round($balance - $amount, 2);

            $rows[] = [
                'no'            => $no,
                'date'          => $date->copy(),
                'amount'        => round($amount, 2),
                'balance_after' => $balance,
            ];

            $date = $date->copy()->addMonths($interval);
            $no++;
        }

        return $rows;
    }

    private function scheduleRemaining(): float
    {
        // Loan tracks a running `balance`; LoanRequest only has the
        // originally requested `amount` (it has no ledger yet).
        if (property_exists($this, 'attributes') && array_key_exists('balance', $this->attributes)) {
            return (float) $this->balance;
        }

        return (float) ($this->amount ?? $this->principal_amount ?? 0);
    }

    private function scheduleStartDate(): Carbon
    {
        if (! empty($this->next_deduction_date)) {
            return Carbon::parse($this->next_deduction_date);
        }

        if (! empty($this->start_date)) {
            return Carbon::parse($this->start_date);
        }

        // LoanRequest has neither — this is an illustrative preview only,
        // dated from today, since the real start date is set when HR
        // actually adds it to the ledger.
        return Carbon::now();
    }
}
