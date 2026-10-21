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
 *  Trial       — every company: N free days (default 7).
 *  Starter     — up to `prepaid_limit` active employees, PRE-paid monthly or yearly.
 *  Business    — more than `prepaid_limit` employees, POST-paid: invoiced every
 *                month for the employees actually active, due after a few days.
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
        $count = fn () => DB::table('employees')->where('company_id', $company->id)->where('employment_status', 'Active')->count();

        if ($company->isDedicated() && $company->database_name && ! Tenancy::isDedicated()) {
            return (int) Tenancy::runInDedicated($company, $count);
        }

        return (int) $count();
    }

    /** "starter" or "business" — what this company should be on today. */
    public function recommendedPlan(Company $company, ?int $employees = null): string
    {
        $employees ??= $this->activeEmployees($company);
        $limit = PlatformSettings::get('prepaid_limit');
        $declared = (int) (config('tenancy.employee_ranges.' . $company->employee_count_range . '.max') ?? 0);

        return ($employees > $limit || $declared > $limit) ? 'business' : 'starter';
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
        ];

        if ($status === 'suspended' || $status === 'cancelled') {
            return ['limited' => true, 'tone' => 'bad', 'headline' => $status === 'cancelled' ? 'Subscription cancelled' : 'Account limited — payment needed'] + $s;
        }

        if ($status === 'trialing') {
            $ends = $company->trial_ends_at ?? $now;
            $s['countdown_to'] = $ends;
            $s['countdown_label'] = 'left in your free trial';
            if ($ends->isPast()) {
                return ['limited' => true, 'tone' => 'bad', 'headline' => 'Your free trial has ended', 'countdown_to' => null] + $s;
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

                // Pre-paid: renewal invoice a few days early
                if ($company->billing_type === 'prepaid' && $company->paid_until
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
