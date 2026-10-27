<?php

namespace App\Services\Partners;

use App\Mail\PartnerMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Models\User;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * ZamPayroll's partner (reseller) programme.
 *
 *  - Anyone can apply at /partners; they get a user account (role "partner") and a
 *    unique code such as ZP-CHANDA25 plus a link (/join/ZP-CHANDA25).
 *  - A company that signs up with the code (typed in, or via the link's cookie)
 *    is linked to that partner for good.
 *  - Every time a linked company pays an invoice, the partner earns the commission
 *    rate (platform setting, or a per-partner override) on the amount before VAT,
 *    for `partner_commission_months` after sign-up (0 = for as long as they pay).
 *  - ZamPayroll pays partners outside the app and marks commission paid in the admin console.
 */
class PartnerProgramme
{
    public const COOKIE = 'zp_partner';
    public const COOKIE_DAYS = 60;

    public function __construct(private Subscriptions $subs)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  Codes                                                               */
    /* ------------------------------------------------------------------ */

    public static function normalise(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $code));
    }

    /** ZP- + first name (up to 8 letters) + 2 digits, e.g. ZP-CHANDA25. Unique. */
    public function makeCode(string $name): string
    {
        $first = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii(Str::before(trim($name), ' '))));
        $first = substr($first !== '' ? $first : 'PARTNER', 0, 8);
        $conn = Tenancy::landlordConnection();

        for ($i = 0; $i < 50; $i++) {
            $code = 'ZP-' . $first . random_int(10, 99);
            if ($i > 30) {
                $code = 'ZP-' . $first . random_int(100, 999);
            }
            if (! DB::connection($conn)->table('partners')->where('code', $code)->exists()) {
                return $code;
            }
        }

        return 'ZP-' . strtoupper(Str::random(8));
    }

    /** An active partner with this code, or null. */
    public function findActive(?string $code): ?Partner
    {
        $code = self::normalise($code);

        return $code === '' ? null : Partner::query()->where('code', $code)->where('status', 'active')->first();
    }

    /* ------------------------------------------------------------------ */
    /*  Applying                                                            */
    /* ------------------------------------------------------------------ */

    /** Create the partner's account and record. Returns the partner (user is ->user). */
    public function apply(array $data): Partner
    {
        $auto = (int) PlatformSettings::get('partner_auto_approve') === 1;
        $landlord = Tenancy::landlordConnection();

        $partner = DB::connection($landlord)->transaction(function () use ($data, $auto) {
            $user = User::on(Tenancy::landlordConnection())->create([
                'company_id' => null,
                'name'       => $data['name'],
                'phone'      => $data['phone'] ?? null,
                'email'      => Str::lower(trim($data['email'])),
                'password'   => Hash::make($data['password']),
                'role'       => User::ROLE_PARTNER,
            ]);

            return Partner::query()->create([
                'user_id'      => $user->id,
                'code'         => $this->makeCode($data['name']),
                'name'         => $data['name'],
                'email'        => $user->email,
                'phone'        => $data['phone'] ?? null,
                'partner_type' => $data['partner_type'],
                'firm_name'    => $data['firm_name'] ?? null,
                'city'         => $data['city'] ?? null,
                'pitch'        => $data['pitch'] ?? null,
                'status'       => $auto ? 'active' : 'pending',
                'approved_at'  => $auto ? now() : null,
            ]);
        });

        Audit::record('partner.applied', $partner, "{$partner->name} applied to the partner programme ({$partner->typeLabel()})" . ($auto ? ', approved automatically' : ', waiting for review'), [], null);
        $this->mail($partner, $auto ? 'welcome' : 'received');

        return $partner;
    }

    public function approve(Partner $partner): void
    {
        $partner->forceFill(['status' => 'active', 'approved_at' => $partner->approved_at ?? now()])->save();
        $this->mail($partner, 'approved');
    }

    /* ------------------------------------------------------------------ */
    /*  Linking companies                                                   */
    /* ------------------------------------------------------------------ */

    /** Link a newly registered company to the partner whose code was used. */
    public function attach(Company $company, Partner $partner, string $how = 'code'): void
    {
        if ($company->partner_id) {
            return; // first partner wins; changes go through the admin console
        }

        $company->forceFill(['partner_id' => $partner->id, 'partner_code' => $partner->code, 'referred_at' => now()])->save();
        Audit::record('partner.client_linked', $company, "{$company->name} signed up with partner code {$partner->code} ({$how})", ['partner_id' => $partner->id], $company->id);
        $this->mail($partner, 'new_client', ['company' => $company->name]);
    }

    /** Admin: move a company to another partner, or remove the link (null). */
    public function reassign(Company $company, ?Partner $partner): void
    {
        $company->forceFill([
            'partner_id'   => $partner?->id,
            'partner_code' => $partner?->code,
            'referred_at'  => $partner ? ($company->referred_at ?? now()) : null,
        ])->save();
    }

    /* ------------------------------------------------------------------ */
    /*  Commission                                                          */
    /* ------------------------------------------------------------------ */

    public function rateFor(Partner $partner): float
    {
        return $partner->commission_rate !== null ? (float) $partner->commission_rate : (float) PlatformSettings::get('partner_commission_rate');
    }

    /**
     * Called once an invoice is marked paid. Creates at most one commission per invoice.
     * Returns the commission, or null when nothing is due.
     */
    public function recordCommission(Invoice $invoice): ?PartnerCommission
    {
        if ($invoice->status !== 'paid') {
            return null;
        }

        $company = Company::on(Tenancy::landlordConnection())->find($invoice->company_id);
        if (! $company || ! $company->partner_id) {
            return null;
        }

        $partner = Partner::query()->find($company->partner_id);
        if (! $partner || $partner->status === 'suspended') {
            return null;
        }

        $months = (int) PlatformSettings::get('partner_commission_months');
        $since = $company->referred_at ?? $company->created_at;
        if ($months > 0 && $since && $invoice->paid_at && $since->copy()->addMonths($months)->lt($invoice->paid_at)) {
            return null;
        }

        $rate = $this->rateFor($partner);
        $base = (float) $invoice->subtotal;
        if ($rate <= 0 || $base <= 0) {
            return null;
        }

        $commission = PartnerCommission::query()->firstOrCreate(['invoice_id' => $invoice->id], [
            'partner_id'     => $partner->id,
            'company_id'     => $company->id,
            'invoice_number' => $invoice->number,
            'base_amount'    => $base,
            'rate'           => $rate,
            'amount'         => round($base * $rate / 100, 2),
            'status'         => 'pending',
        ]);

        if ($commission->wasRecentlyCreated) {
            Audit::record('partner.commission_earned', $commission, "{$partner->code} earned K " . number_format((float) $commission->amount, 2) . " on {$invoice->number}", [], $company->id);
        }

        return $commission;
    }

    /* ------------------------------------------------------------------ */
    /*  Dashboard data                                                      */
    /* ------------------------------------------------------------------ */

    /** The partner's clients with live status (no payroll or employee data). */
    public function clients(Partner $partner): Collection
    {
        $companies = Company::on(Tenancy::landlordConnection())->where('partner_id', $partner->id)->orderByDesc('referred_at')->get();
        $earned = PartnerCommission::query()->where('partner_id', $partner->id)->where('status', '!=', 'void')
            ->selectRaw('company_id, SUM(amount) total')->groupBy('company_id')->pluck('total', 'company_id');

        return $companies->map(function (Company $c) use ($earned) {
            try {
                $state = $this->subs->state($c);
            } catch (\Throwable $e) {
                report($e);
                $state = ['status' => $c->subscription_status, 'employees' => 0, 'limited' => false, 'overdue' => null];
            }

            [$label, $tone] = match (true) {
                (bool) $state['limited']                  => ['Limited', 'bad'],
                ! empty($state['overdue'])                => ['Payment due', 'bad'],
                $state['status'] === 'free'               => ['Free plan', 'free'],
                $state['status'] === 'trialing'           => ['Free trial', 'trial'],
                in_array($state['status'], ['active', 'past_due'], true) => ['Paying', 'ok'],
                default                                   => [ucfirst(str_replace('_', ' ', (string) $state['status'])), 'free'],
            };

            return (object) [
                'id'         => $c->id,
                'name'       => $c->name,
                'joined'     => $c->referred_at ?? $c->created_at,
                'employees'  => (int) ($state['employees'] ?? 0),
                'label'      => $label,
                'tone'       => $tone,
                'trial_ends' => $state['status'] === 'trialing' ? $c->trial_ends_at : null,
                'contact'    => $c->email ?: $c->phone,
                'earned'     => (float) ($earned[$c->id] ?? 0),
                'verified'   => $c->status !== Company::STATUS_PENDING_VERIFICATION,
            ];
        });
    }

    public function stats(Partner $partner, ?Collection $clients = null): array
    {
        $clients ??= $this->clients($partner);
        $commissions = PartnerCommission::query()->where('partner_id', $partner->id);

        return [
            'clients'   => $clients->count(),
            'paying'    => $clients->where('label', 'Paying')->count(),
            'trials'    => $clients->where('label', 'Free trial')->count(),
            'employees' => $clients->sum('employees'),
            'pending'   => (float) (clone $commissions)->where('status', 'pending')->sum('amount'),
            'paid'      => (float) (clone $commissions)->where('status', 'paid')->sum('amount'),
            'this_month' => (float) (clone $commissions)->where('status', '!=', 'void')->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'rate'      => $this->rateFor($partner),
        ];
    }

    /* ------------------------------------------------------------------ */

    public function mail(Partner $partner, string $kind, array $data = []): void
    {
        try {
            Mail::to($partner->email)->send(new PartnerMail($kind, $partner, $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
