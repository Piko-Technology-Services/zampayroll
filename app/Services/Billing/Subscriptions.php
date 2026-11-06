<?php

namespace App\Services\Billing;

use App\Mail\BillingMail;
use App\Mail\BillingProofAdminMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Support\Audit;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * ZamPayroll's subscription rules.
 *
 *  Trial       — every new company gets `trial_months` (2) free, for up to
 *                `free_limit` (10) active employees. Going over 10 needs a
 *                paid plan straight away.
 *  Paid plans  — chosen by active employees (see Plans): Tikula 11–50,
 *                Medium Enterprise 51–150, Corporate 151–300. Teams of 1–10
 *                pay the Tikula price once the trial is over. Enterprise
 *                (301+) is priced by consultation: no invoice is created.
 *  Payment     — Manual: an invoice is emailed with bank / mobile money
 *                details; the customer uploads proof of payment and an admin
 *                confirms it or declines it with a reason.
 *                Online: the customer is sent to the online payment page.
 *  Limited     — trial over (or over 10 employees) or a paid period over and
 *                unpaid past the grace period: everything stays visible but
 *                changes are blocked until a plan is paid. Data is never deleted.
 *
 * Legacy accounts (Starter pre-paid and Business post-paid) keep working as
 * before; new companies can no longer choose them.
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

    public function trialMonths(): int
    {
        return (int) config('billing.trial_months', 2);
    }

    /** The paid plan a team this size needs: a tier key, or "enterprise" (talk to us). */
    public function recommendedPlan(Company $company, ?int $employees = null): string
    {
        $employees ??= $this->activeEmployees($company);

        return Plans::forEmployees($employees) ?? 'enterprise';
    }

    /** Private sub-domains are for paying and larger companies. */
    public function qualifiesForPrivate(Company $company, ?int $employees = null): bool
    {
        $employees ??= $this->activeEmployees($company);
        $declared = (int) (config('tenancy.employee_ranges.' . $company->employee_count_range . '.max') ?? 0);

        return Plans::isPaid($company->plan) || $company->plan === 'business' || $employees > $this->freeLimit() || $declared > $this->freeLimit();
    }

    /** True once the company has had its free trial (it can only be used once). */
    public function trialUsed(Company $company): bool
    {
        return $company->trial_ends_at !== null;
    }

    /** Start the free experience. Called once, when a company is created. */
    public function startTrial(Company $company): void
    {
        $company->forceFill([
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addMonthsNoOverflow($this->trialMonths()),
        ])->save();

        Audit::record('billing.trial_started', $company, 'Free experience started: ' . $this->trialMonths() . ' months, up to ' . $this->freeLimit() . ' active employees', [], $company->id);
        $this->emailCompany($company, 'trial_started', ['months' => $this->trialMonths(), 'limit' => $this->freeLimit()]);
    }

    /**
     * Keeps a trial in step with the team size. Called when employees are added,
     * removed or (de)activated, and by the daily run.
     *  - A company with no trial date gets one now.
     *  - A company limited only because it went over 10 employees during its
     *    trial returns to the trial when it is back at 10 or fewer.
     */
    public function syncFreeTier(Company $company, ?int $employees = null): string
    {
        $company = Company::on(Tenancy::landlordConnection())->find($company->id) ?? $company;
        $status = $company->subscription_status ?: 'trialing';

        if (! $company->billing_type && ! $company->trial_ends_at && in_array($status, ['trialing', 'free'], true)) {
            $this->startTrial($company);

            return $company->fresh()->subscription_status;
        }

        $employees ??= $this->activeEmployees($company);
        if ($status === 'suspended' && ! $company->billing_type && $company->trial_ends_at?->isFuture()
            && $employees <= $this->freeLimit() && ! $this->hasOpenInvoice($company)) {
            $company->forceFill(['subscription_status' => 'trialing'])->save();
            Audit::record('billing.back_to_trial', $company, "Back on the free trial ({$employees} active employees)", [], $company->id);
        }

        return $company->fresh()->subscription_status;
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
        $status = $company->subscription_status ?: 'trialing';
        if ($status === 'free') {
            $status = 'trialing';
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

        if ($status === 'cancelled') {
            return ['limited' => true, 'tone' => 'bad', 'headline' => 'Subscription cancelled'] + $s;
        }

        if ($status === 'suspended') {
            return ['limited' => true, 'tone' => 'bad', 'headline' => 'Account limited — payment needed'] + $s;
        }

        // Free experience
        if ($status === 'trialing' && ! $company->billing_type) {
            $ends = $company->trial_ends_at ?? $company->created_at?->copy()->addMonthsNoOverflow($this->trialMonths()) ?? $now;
            $s['countdown_to'] = $ends;
            $s['countdown_label'] = 'left in your free experience';
            if ($employees > $this->freeLimit()) {
                return ['limited' => true, 'tone' => 'bad', 'headline' => 'Choose a plan — the free experience covers up to ' . $this->freeLimit() . ' employees', 'countdown_to' => null] + $s;
            }
            if ($ends->isPast()) {
                return ['limited' => true, 'tone' => 'bad', 'headline' => 'Your free experience has ended', 'countdown_to' => null] + $s;
            }
            $s['tone'] = $ends->diffInDays($now, true) <= 14 ? 'warn' : 'trial';
            $s['headline'] = 'Free experience';

            return $s;
        }

        // Paid by a subscription invoice (new plans, and renewals)
        if ($company->billing_type === 'prepaid') {
            $until = $company->paid_until ?? $now;
            $s['countdown_to'] = $until;
            $s['countdown_label'] = 'until your next renewal';
            $s['headline'] = Plans::name($company->plan) . ' · paid until ' . $until->format('j M Y');
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

        // Legacy post-paid (Business) accounts
        if ($company->billing_type === 'postpaid') {
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

        // Legacy accounts marked active without a billing type keep full access (as before).
        if ($status === 'active') {
            $s['headline'] = 'Active';

            return $s;
        }

        // Trial ended and no plan chosen yet
        return ['limited' => true, 'tone' => 'bad', 'headline' => 'Choose a plan to continue', 'countdown_to' => null] + $s;
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

    public function hasOpenInvoice(Company $company): bool
    {
        return Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->exists();
    }

    /* ------------------------------------------------------------------ */
    /*  Plans and invoices                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Manual or online checkout for a paid plan. The plan must be the one this
     * team size needs. Re-uses an open invoice for the same plan and cycle.
     */
    public function checkoutInvoice(Company $company, string $tier, string $cycle, string $method): Invoice
    {
        $employees = $this->activeEmployees($company);
        $needed = Plans::forEmployees($employees);
        if ($needed === null) {
            throw new \DomainException('Teams of more than 300 employees are priced by consultation. Please contact ZamPayroll.');
        }
        // Any plan can be paid for, not only the one matching the team size.

        $existing = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('status', 'open')->where('kind', 'subscription')
            ->get()->first(fn ($i) => ($i->meta['plan'] ?? null) === $tier && ($i->meta['cycle'] ?? null) === $cycle);
        if ($existing) {
            $existing->forceFill(['meta' => ['method' => $method] + ($existing->meta ?? [])])->save();

            return $existing;
        }

        return $this->subscriptionInvoice($company, $tier, $cycle, $method, now()->addDays(PlatformSettings::get('invoice_due_days')));
    }

    /** Renewal invoice for a paid period that is about to end. */
    public function renewalInvoice(Company $company, string $cycle): ?Invoice
    {
        // Renew the plan the company is on (it may be above its team size); new companies follow their team size.
        $tier = isset(Plans::TIERS[$company->plan]) ? $company->plan : Plans::forEmployees($this->activeEmployees($company));
        if ($tier === null) {
            return null;
        }

        return $this->subscriptionInvoice($company, $tier, $cycle, 'manual', $company->paid_until?->copy() ?? now()->addDays(PlatformSettings::get('invoice_due_days')));
    }

    /** Kept for older callers: the plan for the current team size. */
    public function prepaidInvoice(Company $company, string $cycle): Invoice
    {
        return $this->checkoutInvoice($company, Plans::forEmployees($this->activeEmployees($company)) ?? 'tikula', $cycle === 'annual' ? 'annual' : 'monthly', 'manual');
    }

    private function subscriptionInvoice(Company $company, string $tier, string $cycle, string $method, Carbon $due): Invoice
    {
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';
        $employees = $this->activeEmployees($company);
        $start = $this->prepaidPeriodStart($company);
        $end = $cycle === 'annual' ? $start->copy()->addYear()->subDay() : $start->copy()->addMonthNoOverflow()->subDay();
        $price = Plans::price($tier, $cycle);
        $period = $cycle === 'annual' ? '12 months' : '1 month';
        $lines = [[
            'description' => 'ZamPayroll ' . Plans::name($tier) . ' — ' . $period . ' (' . $employees . ' active ' . \Illuminate\Support\Str::plural('employee', $employees) . ')',
            'quantity' => 1, 'unit' => $price, 'amount' => $price,
        ]];

        return $this->createInvoice($company, 'subscription', $lines, $start, $end, $due, [
            'cycle' => $cycle, 'plan' => $tier, 'method' => $method, 'employees' => $employees,
        ]);
    }

    /** When a new paid period starts: after the trial or the period already paid for. */
    public function prepaidPeriodStart(Company $company): Carbon
    {
        $candidates = array_filter([now(), $company->paid_until, $company->subscription_status === 'trialing' ? $company->trial_ends_at : null]);

        return collect($candidates)->map(fn ($d) => Carbon::parse($d))->max()->copy()->startOfDay();
    }

    /** Legacy: Business (post-paid) accounts. New companies can no longer start this. */
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

    /** Legacy post-paid usage invoice for the month just ended. */
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

        $invoice = $this->createInvoice($company, 'usage', $lines, $periodStart, $periodEnd, now()->addDays(PlatformSettings::get('invoice_due_days')), ['employees' => $employees, 'plan' => 'business', 'method' => 'manual']);
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
    /*  Manual payments: proof of payment                                   */
    /* ------------------------------------------------------------------ */

    /** The company uploads proof of payment for an open invoice. */
    public function submitProof(Invoice $invoice, UploadedFile $file, ?string $note, ?int $userId): BillingPayment
    {
        if ($invoice->status !== 'open') {
            throw new \DomainException('This invoice is already ' . $invoice->status . '.');
        }
        if (BillingPayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->where('status', 'awaiting_review')->exists()) {
            throw new \DomainException('You have already sent proof of payment for this invoice. We\'re checking it now.');
        }

        $path = $file->store('billing-proofs/' . $invoice->company_id, 'local');

        $payment = BillingPayment::withoutGlobalScopes()->create([
            'company_id'  => $invoice->company_id,
            'invoice_id'  => $invoice->id,
            'provider'    => 'manual',
            'method'      => 'bank_transfer',
            'amount'      => $invoice->total,
            'currency'    => $invoice->currency,
            'status'      => 'awaiting_review',
            'reference'   => $note ? mb_substr($note, 0, 120) : null,
            'proof_path'  => $path,
            'user_id'     => $userId,
        ]);

        Audit::record('billing.proof_submitted', $payment, "Proof of payment sent for {$invoice->number} (K " . number_format((float) $invoice->total, 2) . ')', [], $invoice->company_id);

        $company = Company::on(Tenancy::landlordConnection())->find($invoice->company_id);
        foreach (config('billing.proof_admin_emails', []) as $to) {
            try {
                Mail::to($to)->send(new BillingProofAdminMail($payment, $invoice, $company));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $this->email($invoice, 'proof_received');

        return $payment;
    }

    /** Admin confirms the money has arrived. Activates the plan. */
    public function confirmProof(BillingPayment $payment, ?int $adminId): bool
    {
        if ($payment->status !== 'awaiting_review') {
            return false;
        }

        $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
        $ok = $this->markPaid($invoice, $payment, ['method' => 'bank_transfer', 'reviewed_at' => now(), 'reviewed_by' => $adminId]);
        if (! $ok) {
            // The invoice was already paid another way; keep the proof for the record.
            $payment->forceFill(['status' => 'duplicate', 'reviewed_at' => now(), 'reviewed_by' => $adminId, 'result_explanation' => 'Invoice was already paid.'])->save();
        }

        return $ok;
    }

    /** Admin declines the proof with a reason. The invoice stays open for another upload. */
    public function declineProof(BillingPayment $payment, string $reason, ?int $adminId): void
    {
        if ($payment->status !== 'awaiting_review') {
            throw new \DomainException('This proof has already been reviewed.');
        }

        $payment->forceFill([
            'status' => 'declined', 'result_explanation' => mb_substr($reason, 0, 500),
            'reviewed_at' => now(), 'reviewed_by' => $adminId,
        ])->save();

        $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
        Audit::record('billing.proof_declined', $payment, "Proof for {$invoice?->number} declined: {$reason}", [], $payment->company_id);
        if ($invoice) {
            $this->email($invoice, 'proof_declined', ['reason' => $reason]);
        }
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
                    'plan' => $invoice->meta['plan'] ?? 'tikula', 'billing_type' => 'prepaid', 'billing_cycle' => $cycle,
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
                $this->syncFreeTier($company);
                $company->refresh();

                // Legacy post-paid: bill each completed month
                if ($company->billing_type === 'postpaid' && $company->subscription_status !== 'suspended') {
                    while (($next = $this->nextPostpaidBillDate($company))->lte(now())) {
                        $start = $next->copy()->subMonthNoOverflow();
                        $invoice = $this->postpaidInvoice($company, $start, $next->copy()->subDay());
                        $this->email($invoice, 'invoice');
                        $stats['invoices']++;
                        $company->refresh();
                    }
                }

                // Paid plans: renewal invoice a few days before the paid period ends
                if ($company->billing_type === 'prepaid' && $company->paid_until
                    && $company->paid_until->copy()->subDays(PlatformSettings::get('renewal_notice_days'))->isPast()
                    && ! $this->hasOpenInvoice($company)) {
                    $invoice = $this->renewalInvoice($company, $company->billing_cycle ?: 'monthly');
                    if ($invoice) {
                        $this->email($invoice, 'invoice');
                        $stats['invoices']++;
                    }
                }

                // Trial reminders: 14 days, 2 days before and on expiry
                if ($company->subscription_status === 'trialing' && ! $company->billing_type && $company->trial_ends_at) {
                    $daysLeft = (int) ceil(now()->diffInHours($company->trial_ends_at, false) / 24);
                    if (in_array($daysLeft, [14, 2, 0], true)) {
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

    public function email(Invoice $invoice, string $kind, array $data = []): void
    {
        $company = Company::on(Tenancy::landlordConnection())->find($invoice->company_id);
        $to = $this->billingEmails($company);
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new BillingMail($kind, $company, $invoice, $data));
            if ($kind !== 'proof_declined') {
                $invoice->forceFill(['emailed_at' => now()])->save();
            }
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
