<?php

namespace App\Services\Billing;

use App\Mail\BillingMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Support\Audit;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * ZamPayroll's subscription rules.
 *
 *  Free        — up to `free_limit` active employees (default 10): free, no time limit.
 *  Trial       — starts automatically (N days, default 7) the first time a company
 *                goes over `free_limit`. Dropping back to the limit returns it to Free.
 *  Business    — more than `free_limit` employees, POST-paid: invoiced every
 *                month for the employees actually active, due after a few days.
 *  Starter     — legacy pre-paid plan for small teams; no longer offered (small
 *                teams are free). Existing Starter companies return to Free when
 *                their paid period ends.
 *  Limited     — trial over / pre-paid period over / invoice overdue past the
 *                grace period: everything stays visible but changes are blocked
 *                until the account is paid up (data is never deleted).
 */
class Subscriptions
{
    /* ------------------------------------------------------------------ */
    /*  State                                                               */
    /* ------------------------------------------------------------------ */

    public function company(?int $id = null): ?Company
    {
        $id = $id ?? Tenancy::companyId();

        return $id ? Company::on(Tenancy::landlordConnection())->find($id) : null;
    }

    public function activeEmployees(Company $company): int
    {
        // Board members aren't staff: they're paid through payroll but don't count towards the plan.
        $count = fn () => DB::table('employees')->where('company_id', $company->id)->where('employment_status', 'Active')
            ->where(fn ($q) => $q->whereNull('employment_nature')->orWhereRaw('UPPER(employment_nature) <> ?', ['BOARD']))->count();

        if ($company->isDedicated() && $company->database_name && ! Tenancy::isDedicated()) {
            return (int) Tenancy::runInDedicated($company, $count);
        }

        return (int) $count();
    }

    public function freeLimit(): int
    {
        return PlatformSettings::get('free_limit');
    }

    /** "free" or "business" — what this company should be on today, by its active employees. */
    public function recommendedPlan(Company $company, ?int $employees = null): string
    {
        $employees ??= $this->activeEmployees($company);

        return $employees > $this->freeLimit() ? 'business' : 'free';
    }

    /** Private sub-domains are for larger companies (actual or declared size over the free limit). */
    public function qualifiesForPrivate(Company $company, ?int $employees = null): bool
    {
        $employees ??= $this->activeEmployees($company);
        $declared = (int) (config('tenancy.employee_ranges.' . $company->employee_count_range . '.max') ?? 0);

        return $company->plan === 'business' || $employees > $this->freeLimit() || $declared > $this->freeLimit();
    }

    /** True once the company has had its free trial (it can only be used once). */
    public function trialUsed(Company $company): bool
    {
        return $company->trial_ends_at !== null;
    }

    /**
     * Keep the free plan and the trial in step with the team size. Called whenever
     * employees are added, removed or (de)activated, and by the daily run.
     *
     *  free     + over the limit → 7-day trial starts (only once per company)
     *  trialing + back at/under the limit → free again (trial days already used stay used)
     *  limited after the trial (never paid) + back at/under the limit → free again
     *
     * Paying companies are left alone here; they return to Free at their next bill date.
     */
    public function syncFreeTier(Company $company, ?int $employees = null): string
    {
        $company = Company::on(Tenancy::landlordConnection())->find($company->id) ?? $company;
        $status = $company->subscription_status ?: 'free';
        $employees ??= $this->activeEmployees($company);
        $over = $employees > $this->freeLimit();
        $neverPaid = ! $company->billing_type;

        if ($status === 'trialing' && ! $company->trial_ends_at) {
            // Created outside the app without a trial date: put it on the right footing.
            $company->forceFill(['subscription_status' => 'free'])->save();
            $status = 'free';
        }

        if ($status === 'free' && $over) {
            if ($this->trialUsed($company)) {
                // Trial already used: back to "trialing" with an ended trial → read-only until a plan is chosen.
                $company->forceFill(['subscription_status' => 'trialing'])->save();
                Audit::record('billing.free_limit_exceeded', $company, "Went over the free plan ({$employees} active employees); trial already used — plan needed", [], $company->id);
            } else {
                $days = PlatformSettings::get('trial_days');
                $company->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays($days)])->save();
                Audit::record('billing.trial_started', $company, "Free trial started: {$employees} active employees is over the free plan's " . $this->freeLimit(), [], $company->id);
                $this->emailCompany($company, 'trial_started', ['days' => $days, 'employees' => $employees, 'limit' => $this->freeLimit()]);
            }

            return $company->subscription_status;
        }

        $unpaidInvoices = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->exists();
        if (! $over && $neverPaid && ! $unpaidInvoices && in_array($status, ['trialing', 'suspended'], true)) {
            $company->forceFill(['subscription_status' => 'free'])->save();
            Audit::record('billing.back_to_free', $company, "Back on the free plan ({$employees} active employees)", [], $company->id);
        }

        return $company->subscription_status;
    }

    /** Return a company to the free plan (used when a paying company is back under the limit at its bill date). */
    public function moveToFree(Company $company, string $why): void
    {
        $company->forceFill(['plan' => null, 'billing_type' => null, 'subscription_status' => 'free', 'billing_anchor' => null, 'last_billed_at' => null, 'paid_until' => null])->save();
        Audit::record('billing.back_to_free', $company, $why, [], $company->id);
        $this->emailCompany($company, 'free');
    }

    public function businessMonthly(int $employees): float
    {
        return max(PlatformSettings::get('business_minimum'), $employees * PlatformSettings::get('business_per_employee'));
    }

    /**
     * Everything the banner, billing page and admin console need.
     */
    public function state(Company $company): array
    {
        $now = now();
        $grace = PlatformSettings::get('grace_days');
        $employees = $this->activeEmployees($company);
        $openInvoices = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->orderBy('due_at')->get();
        $overdue = $openInvoices->first(fn ($i) => $i->due_at && $i->due_at->copy()->endOfDay()->lt($now));
        $status = $company->subscription_status ?: 'free';
        if ($status === 'trialing' && ! $company->trial_ends_at) {
            $status = 'free'; // created outside the app with no trial date: treat as free
        }

        $s = [
            'status'      => $status,
            'plan'        => $company->plan,
            'billing_type' => $company->billing_type,
            'employees'   => $employees,
            'recommended' => $this->recommendedPlan($company, $employees),
            'open_invoices' => $openInvoices,
            'overdue'     => $overdue,
            'limited'     => false,
            'countdown_to' => null,
            'countdown_label' => null,
            'headline'    => null,
            'tone'        => 'ok',
            'free_limit'  => $this->freeLimit(),
            'trial_used'  => $this->trialUsed($company),
        ];

        if ($status === 'suspended' || $status === 'cancelled') {
            return ['limited' => true, 'tone' => 'bad', 'headline' => $status === 'cancelled' ? 'Subscription cancelled' : 'Account limited — payment needed'] + $s;
        }

        if ($status === 'free') {
            $s['headline'] = 'Free plan';
            $s['tone'] = $employees >= $this->freeLimit() ? 'trial' : 'ok';
            if ($overdue) { // e.g. a final invoice from a paid plan
                $limitAt = $overdue->due_at->copy()->addDays($grace)->endOfDay();
                $s['tone'] = 'bad';
                $s['headline'] = 'Invoice ' . $overdue->number . ' is overdue';
                $s['limited'] = $limitAt->isPast();
                $s['countdown_to'] = $limitAt;
                $s['countdown_label'] = 'before your account is limited';
            }

            return $s;
        }

        if ($status === 'trialing') {
            $ends = $company->trial_ends_at ?? $now;
            $s['countdown_to'] = $ends;
            $s['countdown_label'] = 'left in your free trial';
            if ($ends->isPast()) {
                return ['limited' => true, 'tone' => 'bad', 'headline' => $employees > $this->freeLimit() ? 'Your free trial has ended' : 'Free trial ended', 'countdown_to' => null] + $s;
            }
            $s['tone'] = $ends->diffInDays($now, true) <= 2 ? 'warn' : 'trial';
            $s['headline'] = 'Free trial';

            return $s;
        }

        if ($company->billing_type === 'prepaid') {
            $until = $company->paid_until ?? $now;
            $s['countdown_to'] = $until;
            $s['countdown_label'] = 'until your next payment';
            $s['headline'] = 'Paid until ' . $until->format('j M Y');
            if ($until->isPast()) {
                $s['tone'] = 'bad';
                $s['headline'] = 'Payment due — renew to keep full access';
                $s['limited'] = $until->copy()->addDays($grace)->isPast();
                $s['countdown_to'] = $until->copy()->addDays($grace);
                $s['countdown_label'] = 'before your account is limited';
            } elseif ($until->diffInDays($now, true) <= PlatformSettings::get('renewal_notice_days')) {
                $s['tone'] = 'warn';
            }

            return $s;
        }

        // post-paid
        $next = $this->nextPostpaidBillDate($company);
        $s['countdown_to'] = $next;
        $s['countdown_label'] = 'until your next invoice';
        $s['estimate'] = $this->businessMonthly($employees);
        $s['headline'] = 'Next invoice ' . $next->format('j M Y');
        if ($overdue) {
            $s['tone'] = 'bad';
            $s['headline'] = 'Invoice ' . $overdue->number . ' is overdue';
            $limitAt = $overdue->due_at->copy()->addDays($grace)->endOfDay();
            $s['limited'] = $limitAt->isPast();
            $s['countdown_to'] = $limitAt;
            $s['countdown_label'] = 'before your account is limited';
        }

        return $s;
    }

    public function nextPostpaidBillDate(Company $company): Carbon
    {
        $anchor = $company->billing_anchor ? Carbon::parse($company->billing_anchor) : now();
        $next = $company->last_billed_at ? Carbon::parse($company->last_billed_at)->startOfDay()->addMonthNoOverflow() : $anchor->copy()->addMonthNoOverflow();

        return $next->startOfDay();
    }

    public function isLimited(?Company $company): bool
    {
        return $company ? $this->state($company)['limited'] : false;
    }

    /* ------------------------------------------------------------------ */
    /*  Starting a plan                                                     */
    /* ------------------------------------------------------------------ */

    /** Pre-paid: create (or reuse) the invoice for the next period. */
    public function prepaidInvoice(Company $company, string $cycle): Invoice
    {
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';
        $existing = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->where('kind', 'subscription')
            ->get()->first(fn ($i) => ($i->meta['cycle'] ?? null) === $cycle);
        if ($existing) {
            return $existing;
        }

        $price = $cycle === 'annual' ? PlatformSettings::get('starter_annual') : PlatformSettings::get('starter_monthly');
        $start = $this->prepaidPeriodStart($company);
        $end = $cycle === 'annual' ? $start->copy()->addYear()->subDay() : $start->copy()->addMonthNoOverflow()->subDay();
        $lines = [['description' => 'ZamPayroll Starter — ' . ($cycle === 'annual' ? '12 months' : '1 month') . ' (up to ' . PlatformSettings::get('prepaid_limit') . ' employees)', 'quantity' => 1, 'unit' => $price, 'amount' => $price]];
        if ($company->isDedicated() && PlatformSettings::get('private_workspace_monthly') > 0) {
            $months = $cycle === 'annual' ? 12 : 1;
            $fee = PlatformSettings::get('private_workspace_monthly');
            $lines[] = ['description' => 'Private workspace (' . $company->subdomain . ')', 'quantity' => $months, 'unit' => $fee, 'amount' => $fee * $months];
        }

        return $this->createInvoice($company, 'subscription', $lines, $start, $end, now()->toDateString() > $start->toDateString() ? now() : $start, ['cycle' => $cycle, 'plan' => 'starter']);
    }

    public function prepaidPeriodStart(Company $company): Carbon
    {
        $candidates = array_filter([now(), $company->paid_until, $company->subscription_status === 'trialing' ? $company->trial_ends_at : null]);

        return collect($candidates)->map(fn ($d) => Carbon::parse($d))->max()->copy()->startOfDay();
    }

    /** Business companies switch to post-paid billing (no payment needed today). */
    public function startPostpaid(Company $company): Company
    {
        $start = ($company->subscription_status === 'trialing' && $company->trial_ends_at?->isFuture()) ? $company->trial_ends_at->copy()->startOfDay() : now()->startOfDay();

        $company->forceFill([
            'plan'                => 'business',
            'billing_type'        => 'postpaid',
            'subscription_status' => 'active',
            'billing_anchor'      => $start->toDateString(),
            'last_billed_at'      => null,
        ])->save();

        Audit::record('billing.postpaid_started', $company, 'Business (post-paid) plan started, first invoice ' . $this->nextPostpaidBillDate($company)->format('j M Y'), [], $company->id);

        return $company;
    }

    /** Post-paid usage invoice for the month just ended. */
    public function postpaidInvoice(Company $company, Carbon $periodStart, Carbon $periodEnd): Invoice
    {
        $employees = max(1, $this->activeEmployees($company));
        $rate = PlatformSettings::get('business_per_employee');
        $minimum = PlatformSettings::get('business_minimum');
        $amount = $employees * $rate;
        $lines = [['description' => "ZamPayroll Business — {$employees} active employees ({$periodStart->format('j M')} – {$periodEnd->format('j M Y')})", 'quantity' => $employees, 'unit' => $rate, 'amount' => $amount]];
        if ($amount < $minimum) {
            $lines[] = ['description' => 'Minimum monthly charge top-up', 'quantity' => 1, 'unit' => $minimum - $amount, 'amount' => $minimum - $amount];
        }
        if ($company->isDedicated() && PlatformSettings::get('private_workspace_monthly') > 0) {
            $fee = PlatformSettings::get('private_workspace_monthly');
            $lines[] = ['description' => 'Private workspace (' . $company->subdomain . ')', 'quantity' => 1, 'unit' => $fee, 'amount' => $fee];
        }

        $invoice = $this->createInvoice($company, 'usage', $lines, $periodStart, $periodEnd, now()->addDays(PlatformSettings::get('invoice_due_days')), ['employees' => $employees, 'plan' => 'business']);
        $company->forceFill(['last_billed_at' => $periodEnd->copy()->addDay()->startOfDay()])->save();

        return $invoice;
    }

    public function createInvoice(Company $company, string $kind, array $lines, ?Carbon $start, ?Carbon $end, Carbon $due, array $meta = []): Invoice
    {
        return DB::connection(Tenancy::landlordConnection())->transaction(function () use ($company, $kind, $lines, $start, $end, $due, $meta) {
            $subtotal = round(collect($lines)->sum('amount'), 2);
            $vatRate = PlatformSettings::get('vat_rate');
            $vat = round($subtotal * $vatRate / 100, 2);

            $invoice = Invoice::withoutGlobalScopes()->create([
                'company_id'   => $company->id,
                'number'       => $this->nextNumber(),
                'kind'         => $kind,
                'period_start' => $start?->toDateString(),
                'period_end'   => $end?->toDateString(),
                'lines'        => $lines,
                'subtotal'     => $subtotal,
                'vat_rate'     => $vatRate,
                'vat'          => $vat,
                'total'        => $subtotal + $vat,
                'currency'     => config('billing.currency', 'ZMW'),
                'status'       => 'open',
                'due_at'       => $due->toDateString(),
                'meta'         => $meta,
            ]);

            Audit::record('billing.invoice_created', $invoice, "Invoice {$invoice->number} for K " . number_format((float) $invoice->total, 2), [], $company->id);

            return $invoice;
        });
    }

    private function nextNumber(): string
    {
        $year = now()->year;
        $last = Invoice::withoutGlobalScopes()->where('number', 'like', "ZP-{$year}-%")->lockForUpdate()->orderByDesc('id')->value('number');
        $n = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('ZP-%d-%05d', $year, $n);
    }

    /* ------------------------------------------------------------------ */
    /*  Getting paid                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Mark an invoice paid exactly once and extend the subscription.
     * Safe to call repeatedly (callback + redirect + polling can all arrive).
     */
    public function markPaid(Invoice $invoice, BillingPayment $payment, array $attrs = []): bool
    {
        $done = DB::connection(Tenancy::landlordConnection())->transaction(function () use ($invoice, $payment, $attrs) {
            $invoice = Invoice::withoutGlobalScopes()->lockForUpdate()->find($invoice->id);
            $payment = BillingPayment::withoutGlobalScopes()->lockForUpdate()->find($payment->id);

            if ($payment->status === 'paid') {
                return false;
            }

            $payment->forceFill(['status' => 'paid', 'paid_at' => now()] + $attrs)->save();

            if ($invoice->status === 'paid') {
                // Paid twice (e.g. two tabs): keep the record so ZamPayroll can refund it.
                Audit::record('billing.duplicate_payment', $payment, "Second payment received for {$invoice->number} — refund or credit it", [], $invoice->company_id);

                return false;
            }

            $invoice->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            $company = Company::on(Tenancy::landlordConnection())->lockForUpdate()->find($invoice->company_id);

            if ($invoice->kind === 'subscription') {
                $cycle = $invoice->meta['cycle'] ?? 'monthly';
                $from = $this->prepaidPeriodStart($company);
                $until = $cycle === 'annual' ? $from->copy()->addYear() : $from->copy()->addMonthNoOverflow();
                $company->forceFill([
                    'plan' => 'starter', 'billing_type' => 'prepaid', 'billing_cycle' => $cycle,
                    'subscription_status' => 'active', 'paid_until' => $until,
                ])->save();
            } elseif (! Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')
                ->whereDate('due_at', '<', now()->subDays(PlatformSettings::get('grace_days')))->exists()) {
                $company->forceFill(['subscription_status' => 'active'])->save();
            }

            Audit::record('billing.payment_received', $payment, 'K ' . number_format((float) $payment->amount, 2) . " paid for {$invoice->number} by " . str_replace('_', ' ', (string) $payment->method), [], $invoice->company_id);

            return true;
        });

        if ($done) {
            $this->email($invoice->fresh(), 'receipt');
            try {
                app(\App\Services\Partners\PartnerProgramme::class)->recordCommission($invoice->fresh());
            } catch (\Throwable $e) {
                report($e); // never let commission bookkeeping break a payment
            }
        }

        return $done;
    }

    /** Ask DPO about a pending payment; marks it paid/failed. Returns the payment's status. */
    public function refresh(BillingPayment $payment, DpoClient $dpo): string
    {
        if ($payment->status !== 'pending' || ! $payment->trans_token) {
            return $payment->status;
        }

        $result = $dpo->verify($payment->trans_token);
        $payment->forceFill(['last_checked_at' => now(), 'result_code' => $result['code'], 'result_explanation' => $result['message']])->save();

        if ($result['paid']) {
            $this->markPaid($payment->invoice()->withoutGlobalScopes()->first(), $payment);

            return 'paid';
        }

        if (! $result['pending']) {
            $payment->forceFill(['status' => in_array($result['code'], ['904'], true) ? 'cancelled' : 'failed'])->save();
        }

        return $payment->fresh()->status;
    }

    /* ------------------------------------------------------------------ */
    /*  Daily run (scheduler)                                               */
    /* ------------------------------------------------------------------ */

    public function runDaily(?callable $log = null): array
    {
        $log ??= fn () => null;
        $stats = ['invoices' => 0, 'limited' => 0, 'reminders' => 0, 'verified' => 0];
        $landlord = Tenancy::landlordConnection();

        foreach (Company::on($landlord)->whereNotIn('subscription_status', ['cancelled'])->get() as $company) {
            try {
                // Free plan ↔ trial, in case an employee change was missed
                if ($company->subscription_status !== $this->syncFreeTier($company)) {
                    $company->refresh();
                }

                // Paying companies back at/under the free limit on their bill date → free (no invoice)
                $small = fn () => $this->activeEmployees($company) <= $this->freeLimit();
                if ($company->billing_type === 'postpaid' && $company->subscription_status !== 'suspended'
                    && $this->nextPostpaidBillDate($company)->lte(now()) && $small()) {
                    $this->moveToFree($company, 'Back on the free plan at the bill date (' . $this->activeEmployees($company) . ' active employees)');
                    $company->refresh();
                }
                if ($company->billing_type === 'prepaid' && $company->paid_until && $company->paid_until->isPast() && $small()
                    && ! Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->exists()) {
                    $this->moveToFree($company, 'Starter period ended; back on the free plan (' . $this->activeEmployees($company) . ' active employees)');
                    $company->refresh();
                }

                // Post-paid: bill each completed month
                if ($company->billing_type === 'postpaid' && $company->subscription_status !== 'suspended') {
                    while (($next = $this->nextPostpaidBillDate($company))->lte(now())) {
                        $start = $next->copy()->subMonthNoOverflow();
                        $invoice = $this->postpaidInvoice($company, $start, $next->copy()->subDay());
                        $this->email($invoice, 'invoice');
                        $stats['invoices']++;
                        $company->refresh();
                    }
                }

                // Pre-paid: renewal invoice a few days early (only if the team is over the free limit)
                if ($company->billing_type === 'prepaid' && $company->paid_until && ! $small()
                    && $company->paid_until->copy()->subDays(PlatformSettings::get('renewal_notice_days'))->isPast()
                    && ! Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->where('kind', 'subscription')->exists()) {
                    $invoice = $this->prepaidInvoice($company, $company->billing_cycle ?: 'monthly');
                    $invoice->forceFill(['due_at' => $company->paid_until->toDateString()])->save();
                    $this->email($invoice, 'invoice');
                    $stats['invoices']++;
                }

                // Trial reminders: 2 days before and on expiry
                if ($company->subscription_status === 'trialing' && $company->trial_ends_at) {
                    $daysLeft = (int) ceil(now()->diffInHours($company->trial_ends_at, false) / 24);
                    if (in_array($daysLeft, [2, 0], true)) {
                        $this->emailCompany($company, 'trial', ['days_left' => max(0, $daysLeft)]);
                        $stats['reminders']++;
                    }
                }

                // Limit accounts that are past the grace period
                $state = $this->state($company);
                if ($state['limited'] && $company->subscription_status !== 'suspended') {
                    $company->forceFill(['subscription_status' => 'suspended'])->save();
                    Audit::record('billing.limited', $company, 'Account limited: ' . $state['headline'], [], $company->id);
                    $this->emailCompany($company, 'limited');
                    $stats['limited']++;
                } elseif (! $state['limited'] && $state['overdue'] && $company->subscription_status === 'active') {
                    $company->forceFill(['subscription_status' => 'past_due'])->save();
                }

                // Overdue reminders (every 3 days, max 3)
                foreach ($state['open_invoices'] as $inv) {
                    if ($inv->isOverdue() && $inv->reminders_sent < 3 && (! $inv->emailed_at || $inv->emailed_at->lt(now()->subDays(3)))) {
                        $this->email($inv, 'reminder');
                        $inv->increment('reminders_sent');
                        $stats['reminders']++;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $log("Company {$company->id}: {$e->getMessage()}");
            }
        }

        // Catch DPO payments whose callback never arrived
        $dpo = app(DpoClient::class);
        BillingPayment::withoutGlobalScopes()->where('status', 'pending')->whereNotNull('trans_token')
            ->where('created_at', '>', now()->subDays(2))->where('created_at', '<', now()->subMinutes(2))
            ->each(function ($p) use ($dpo, &$stats) {
                $this->refresh($p, $dpo);
                $stats['verified']++;
            });

        return $stats;
    }

    /* ------------------------------------------------------------------ */

    public function email(Invoice $invoice, string $kind): void
    {
        $company = Company::on(Tenancy::landlordConnection())->find($invoice->company_id);
        $to = $this->billingEmails($company);
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new BillingMail($kind, $company, $invoice));
            $invoice->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function emailCompany(Company $company, string $kind, array $data = []): void
    {
        if (! $to = $this->billingEmails($company)) {
            return;
        }

        try {
            Mail::to($to)->send(new BillingMail($kind, $company, null, $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Billing email + company administrators. */
    public function billingEmails(?Company $company): array
    {
        if (! $company) {
            return [];
        }

        $admins = $company->isDedicated() && $company->database_name && ! Tenancy::isDedicated()
            ? Tenancy::runInDedicated($company, fn () => DB::table('users')->where('role', 'company_admin')->pluck('email')->all())
            : DB::connection(Tenancy::landlordConnection())->table('users')->where('company_id', $company->id)->where('role', 'company_admin')->pluck('email')->all();

        return array_values(array_unique(array_filter(array_merge([$company->billing_email], $admins))));
    }
}
