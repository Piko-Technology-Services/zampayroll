<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SupportTicketMail;
use App\Models\AuditLog;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Billing\DpoClient;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Services\TenantUpgrader;
use App\Support\Audit;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * ZamPayroll's own admin console (/platform): every client company, their
 * subscriptions, invoices, DPO payments, support tickets and settings.
 * Only platform administrators (no company) can open it — see EnsurePlatformAdmin.
 */
class PlatformController extends Controller
{
    public function __construct(private Subscriptions $subs)
    {
    }

    private function companies()
    {
        return Company::on(Tenancy::landlordConnection());
    }

    /* ------------------------------------------------------------------ */
    /*  Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    public function dashboard()
    {
        $companies = $this->companies()->get();
        $paid = BillingPayment::query()->where('status', 'paid');
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $revenue = $months->map(fn (Carbon $m) => [
            'label' => $m->format('M'),
            'total' => (float) (clone $paid)->whereBetween('paid_at', [$m, $m->copy()->endOfMonth()])->sum('amount'),
        ]);

        $mrr = $companies->where('subscription_status', 'active')->sum(function ($c) {
            if ($c->billing_type === 'prepaid') {
                return $c->billing_cycle === 'annual' ? PlatformSettings::get('starter_annual') / 12 : PlatformSettings::get('starter_monthly');
            }
            $last = Invoice::query()->where('company_id', $c->id)->where('kind', 'usage')->latest('id')->value('total');

            return (float) ($last ?? PlatformSettings::get('business_minimum'));
        });

        return view('platform.dashboard', [
            'counts' => [
                'companies' => $companies->count(),
                'trialing'  => $companies->where('subscription_status', 'trialing')->count(),
                'active'    => $companies->where('subscription_status', 'active')->count(),
                'trouble'   => $companies->whereIn('subscription_status', ['past_due', 'suspended'])->count(),
                'dedicated' => $companies->where('tenancy_mode', Company::MODE_DEDICATED)->count(),
            ],
            'mrr'          => $mrr,
            'thisMonth'    => (float) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'outstanding'  => (float) Invoice::query()->where('status', 'open')->sum('total'),
            'revenue'      => $revenue,
            'trialsEnding' => $companies->where('subscription_status', 'trialing')->filter(fn ($c) => $c->trial_ends_at && $c->trial_ends_at->between(now()->subDays(3), now()->addDays(3)))->sortBy('trial_ends_at'),
            'overdue'      => Invoice::query()->with('company')->where('status', 'open')->whereDate('due_at', '<', now())->orderBy('due_at')->take(8)->get(),
            'payments'     => BillingPayment::query()->with('company')->latest('id')->take(8)->get(),
            'requests'     => $companies->whereNotNull('requested_subdomain'),
            'signups'      => $companies->sortByDesc('created_at')->take(6),
            'openTickets'  => SupportTicket::query()->with('company')->whereIn('status', ['open'])->orderByDesc('priority')->latest('last_reply_at')->take(6)->get(),
            'dpoReady'     => app(DpoClient::class)->configured(),
            'dpoMode'      => app(DpoClient::class)->fake() ? 'test (fake)' : config('dpo.mode'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Companies                                                           */
    /* ------------------------------------------------------------------ */

    public function companiesIndex(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');

        $companies = $this->companies()
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('company_code', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($status, fn ($w) => $w->where('subscription_status', $status))
            ->orderByDesc('created_at')->paginate(30)->withQueryString();

        $landlord = Tenancy::landlordConnection();
        $ids = $companies->pluck('id');
        $staff = DB::connection($landlord)->table('users')->whereIn('company_id', $ids)->selectRaw('company_id, COUNT(*) n')->groupBy('company_id')->pluck('n', 'company_id');
        $people = DB::connection($landlord)->table('employees')->whereIn('company_id', $ids)->where('employment_status', 'Active')->selectRaw('company_id, COUNT(*) n')->groupBy('company_id')->pluck('n', 'company_id');

        return view('platform.companies', compact('companies', 'q', 'status', 'staff', 'people'));
    }

    public function companyShow(Company $company)
    {
        $company = $this->companies()->findOrFail($company->id);
        $state = $this->subs->state($company);
        $users = $company->isDedicated() && $company->database_name
            ? Tenancy::runInDedicated($company, fn () => DB::table('users')->orderBy('name')->get())
            : DB::connection(Tenancy::landlordConnection())->table('users')->where('company_id', $company->id)->orderBy('name')->get();

        return view('platform.company', [
            'company'  => $company,
            'state'    => $state,
            'users'    => $users,
            'invoices' => Invoice::query()->where('company_id', $company->id)->latest('id')->get(),
            'payments' => BillingPayment::query()->where('company_id', $company->id)->latest('id')->take(20)->get(),
            'tickets'  => SupportTicket::query()->where('company_id', $company->id)->latest('updated_at')->take(10)->get(),
            'audit'    => AuditLog::query()->where('company_id', $company->id)->latest('id')->take(25)->get(),
            'prices'   => PlatformSettings::all(),
        ]);
    }

    /** All subscription actions from the company page. */
    public function companyAction(Request $request, Company $company)
    {
        $company = $this->companies()->findOrFail($company->id);
        $action = $request->input('action');

        switch ($action) {
            case 'extend_trial':
                $days = (int) $request->validate(['days' => 'required|integer|min:1|max:90'])['days'];
                $from = $company->trial_ends_at && $company->trial_ends_at->isFuture() ? $company->trial_ends_at : now();
                $company->forceFill(['trial_ends_at' => $from->copy()->addDays($days), 'subscription_status' => 'trialing'])->save();
                $msg = "Trial extended by {$days} days (now ends {$company->trial_ends_at->format('j M Y')}).";
                break;

            case 'extend_paid':
                $days = (int) $request->validate(['days' => 'required|integer|min:1|max:400'])['days'];
                $from = $company->paid_until && $company->paid_until->isFuture() ? $company->paid_until : now();
                $company->forceFill(['paid_until' => $from->copy()->addDays($days), 'subscription_status' => 'active', 'billing_type' => $company->billing_type ?: 'prepaid', 'plan' => $company->plan ?: 'starter'])->save();
                $msg = "Paid access extended to {$company->paid_until->format('j M Y')}.";
                break;

            case 'set_plan':
                $data = $request->validate(['plan' => 'required|in:starter,business', 'billing_cycle' => 'nullable|in:monthly,annual']);
                if ($data['plan'] === 'business') {
                    $this->subs->startPostpaid($company);
                } else {
                    $company->forceFill(['plan' => 'starter', 'billing_type' => 'prepaid', 'billing_cycle' => $data['billing_cycle'] ?? 'monthly'])->save();
                }
                $msg = 'Plan set to ' . ucfirst($data['plan']) . '.';
                break;

            case 'status':
                $data = $request->validate(['status' => 'required|in:trialing,active,past_due,suspended,cancelled']);
                $company->forceFill(['subscription_status' => $data['status']])->save();
                $msg = 'Status changed to ' . $data['status'] . '.';
                break;

            case 'invoice':
                $data = $request->validate(['description' => 'required|string|max:200', 'amount' => 'required|numeric|min:1', 'due_days' => 'nullable|integer|min:0|max:60']);
                $inv = $this->subs->createInvoice($company, 'other', [['description' => $data['description'], 'quantity' => 1, 'unit' => (float) $data['amount'], 'amount' => (float) $data['amount']]],
                    null, null, now()->addDays((int) ($data['due_days'] ?? 7)));
                $this->subs->email($inv, 'invoice');
                $msg = "Invoice {$inv->number} created and emailed.";
                break;

            case 'notes':
                $company->forceFill(['billing_notes' => $request->validate(['billing_notes' => 'nullable|string|max:5000'])['billing_notes']])->save();
                $msg = 'Notes saved.';
                break;

            case 'upgrade':
                $sub = strtolower((string) $request->validate(['subdomain' => 'required|string|max:32'])['subdomain']);
                try {
                    app(TenantUpgrader::class)->upgrade($company, $sub);
                } catch (\Throwable $e) {
                    report($e);

                    return back()->with('error', 'Upgrade failed — nothing was changed in the shared database. ' . $e->getMessage());
                }
                $this->subs->emailCompany($company->fresh(), 'workspace');
                $msg = "Moved to {$sub}." . Tenancy::centralDomain() . '. Users sign in there from now on.';
                break;

            case 'reject_upgrade':
                $company->forceFill(['requested_subdomain' => null, 'subdomain_requested_at' => null])->save();
                $msg = 'Private workspace request declined.';
                break;

            default:
                abort(422);
        }

        Audit::record('platform.' . $action, $company, $msg, $request->except(['_token', 'action']), $company->id);

        return back()->with('success', $msg);
    }

    public function invoiceAction(Request $request, Invoice $invoice)
    {
        $action = $request->input('action');

        if ($action === 'record_payment') {
            $data = $request->validate(['method' => 'required|in:bank,cash,mobile_money,card', 'reference' => 'required|string|max:120', 'amount' => 'nullable|numeric|min:0.01']);
            abort_if($invoice->status !== 'open', 422, 'Invoice is not open.');
            $payment = BillingPayment::create([
                'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'provider' => 'manual', 'method' => $data['method'],
                'amount' => $data['amount'] ?? $invoice->total, 'currency' => $invoice->currency, 'status' => 'pending',
                'reference' => $data['reference'], 'user_id' => $request->user()->id,
            ]);
            $this->subs->markPaid($invoice, $payment);
            $msg = "Payment recorded for {$invoice->number}.";
        } elseif ($action === 'void') {
            abort_if($invoice->status === 'paid', 422, 'Paid invoices can\'t be voided.');
            $invoice->update(['status' => 'void']);
            $msg = "{$invoice->number} voided.";
        } elseif ($action === 'resend') {
            $this->subs->email($invoice, $invoice->status === 'paid' ? 'receipt' : ($invoice->isOverdue() ? 'reminder' : 'invoice'));
            $msg = "{$invoice->number} emailed again.";
        } elseif ($action === 'check') {
            foreach ($invoice->payments()->where('status', 'pending')->get() as $p) {
                $this->subs->refresh($p, app(DpoClient::class));
            }
            $msg = 'Checked with DPO: ' . $invoice->fresh()->status . '.';
        } else {
            abort(422);
        }

        Audit::record('platform.invoice_' . $action, $invoice, $msg, $request->except(['_token', 'action']), $invoice->company_id);

        return back()->with('success', $msg);
    }

    public function invoices(Request $request)
    {
        $status = $request->query('status');

        return view('platform.invoices', [
            'invoices' => Invoice::query()->with('company')->when($status === 'overdue', fn ($q) => $q->where('status', 'open')->whereDate('due_at', '<', now()))
                ->when($status && $status !== 'overdue', fn ($q) => $q->where('status', $status))->latest('id')->paginate(40)->withQueryString(),
            'payments' => BillingPayment::query()->with('company', 'invoice')->latest('id')->take(30)->get(),
            'status'   => $status,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Support tickets                                                     */
    /* ------------------------------------------------------------------ */

    public function tickets(Request $request)
    {
        $status = $request->query('status', 'open');

        return view('platform.tickets', [
            'tickets' => SupportTicket::query()->with('company')->withCount('messages')
                ->when($status !== 'all', fn ($q) => $q->whereIn('status', $status === 'open' ? ['open', 'waiting'] : [$status]))
                ->orderByRaw("FIELD(priority, 'urgent', 'normal', 'low')")->latest('last_reply_at')->paginate(40)->withQueryString(),
            'status'  => $status,
        ]);
    }

    public function ticket(SupportTicket $ticket)
    {
        return view('platform.ticket', ['ticket' => $ticket->load('messages', 'company')]);
    }

    public function ticketReply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['body' => 'nullable|string|max:5000', 'status' => 'nullable|in:open,waiting,resolved,closed', 'priority' => 'nullable|in:low,normal,urgent']);

        if (filled($data['body'] ?? null)) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id, 'company_id' => $ticket->company_id, 'user_id' => $request->user()->id,
                'author_name' => $request->user()->name, 'from_staff' => true, 'body' => $data['body'],
            ]);
            $ticket->forceFill(['last_reply_at' => now(), 'last_reply_by_staff' => true, 'status' => $data['status'] ?? 'waiting'])->save();
            $this->mailCustomer($ticket);
        } elseif (! empty($data['status'])) {
            $ticket->update(['status' => $data['status']]);
        }
        if (! empty($data['priority'])) {
            $ticket->update(['priority' => $data['priority']]);
        }

        Audit::record('platform.ticket_reply', $ticket, "Ticket {$ticket->number} " . (filled($data['body'] ?? null) ? 'answered' : 'updated'), [], $ticket->company_id);

        return back()->with('success', 'Ticket updated.');
    }

    private function mailCustomer(SupportTicket $ticket): void
    {
        $company = $this->companies()->find($ticket->company_id);
        $user = $company?->isDedicated()
            ? Tenancy::runInDedicated($company, fn () => DB::table('users')->where('id', $ticket->user_id)->first())
            : DB::connection(Tenancy::landlordConnection())->table('users')->where('id', $ticket->user_id)->first();
        if (! $user?->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new SupportTicketMail($ticket->fresh(['messages', 'company']), 'staff_reply'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Settings & audit                                                    */
    /* ------------------------------------------------------------------ */

    public function settings()
    {
        return view('platform.settings', [
            'values'   => PlatformSettings::all(),
            'fields'   => PlatformSettings::EDITABLE,
            'dpo'      => app(DpoClient::class),
            'admins'   => User::on(Tenancy::landlordConnection())->where('role', 'platform_admin')->whereNull('company_id')->get(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $rules = collect(PlatformSettings::EDITABLE)->map(fn ($f) => $f[1] === 'int' ? 'required|integer|min:0|max:100000' : 'required|numeric|min:0|max:10000000')->all();
        $data = $request->validate($rules);
        $before = PlatformSettings::all();
        PlatformSettings::set($data);
        Audit::record('platform.settings_saved', null, 'Pricing & billing settings changed', ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Settings saved. New invoices use these values.');
    }

    public function audit(Request $request)
    {
        return view('platform.audit', [
            'logs' => AuditLog::query()->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('action', 'like', "%{$v}%")->orWhere('summary', 'like', "%{$v}%")))
                ->latest('id')->paginate(50)->withQueryString(),
            'companies' => $this->companies()->pluck('name', 'id'),
        ]);
    }
}
