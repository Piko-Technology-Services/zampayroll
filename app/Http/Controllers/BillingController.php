<?php

namespace App\Http\Controllers;

use App\Mail\BillingMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Billing\DpoClient;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Settings → Billing: plan, trial / payment countdown, invoices, and paying
 * with mobile money (in-app prompt) or card (DPO popup).
 */
class BillingController extends Controller
{
    public function __construct(private Subscriptions $subs, private DpoClient $dpo)
    {
    }

    public function index(Request $request)
    {
        $company = $this->subs->company();
        $state = $this->subs->state($company);

        return view('billing.index', [
            'company'   => $company,
            'state'     => $state,
            'invoices'  => Invoice::query()->latest('id')->get(),
            'payments'  => BillingPayment::query()->latest('id')->take(20)->get(),
            'prices'    => PlatformSettings::all(),
            'networks'  => config('dpo.networks'),
            'dpoReady'  => $this->dpo->configured(),
            'payInvoice' => $request->query('pay') ? Invoice::query()->find($request->query('pay')) : null,
        ]);
    }

    /** Starter (pre-paid): create the invoice and open the payment window. */
    public function prepaid(Request $request)
    {
        $request->validate(['cycle' => 'required|in:monthly,annual']);
        $company = $this->subs->company();
        $state = $this->subs->state($company);

        if ($state['employees'] > PlatformSettings::get('prepaid_limit')) {
            return back()->with('error', 'You have ' . $state['employees'] . ' active employees, so the Business plan applies.');
        }

        $invoice = $this->subs->prepaidInvoice($company, $request->input('cycle'));

        return redirect()->route('billing.index', ['pay' => $invoice->id]);
    }

    /** Business (post-paid): start now, pay after each month. */
    public function postpaid(Request $request)
    {
        $request->validate(['accept' => 'accepted'], ['accept.accepted' => 'Please accept the post-paid terms.']);
        $company = $this->subs->company();
        $this->subs->startPostpaid($company);

        return redirect()->route('billing.index')->with('success', 'Business plan started. Your first invoice arrives on ' . $this->subs->nextPostpaidBillDate($company->fresh())->format('j M Y') . '.');
    }

    public function details(Request $request)
    {
        $data = $request->validate(['billing_email' => 'nullable|email|max:180']);
        $company = $this->subs->company();
        $company->forceFill(['billing_email' => $data['billing_email'] ?? null])->save();
        Audit::record('billing.details_updated', $company, 'Billing email set to ' . ($data['billing_email'] ?? '—'), [], $company->id);

        return back()->with('success', 'Billing email saved.');
    }

    /** Premium companies (over the Starter limit) can ask for their own private sub-domain. */
    public function requestPrivate(Request $request)
    {
        $company = $this->subs->company();
        $state = $this->subs->state($company);
        abort_if($company->isDedicated(), 422, 'You already have a private workspace.');

        if ($state['recommended'] !== 'business' && $company->plan !== 'business') {
            return back()->with('error', 'Private workspaces are for companies with more than ' . PlatformSettings::get('prepaid_limit') . ' employees.');
        }

        $request->merge(['subdomain' => Str::lower(trim((string) $request->input('subdomain')))]);
        $data = $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', function ($a, $v, $fail) use ($company) {
                $reserved = config('tenancy.reserved_subdomains', ['www', 'app', 'admin', 'api', 'mail', 'platform']);
                if (in_array($v, $reserved, true) || Company::on(Tenancy::landlordConnection())->where('id', '!=', $company->id)
                        ->where(fn ($q) => $q->where('subdomain', $v)->orWhere('requested_subdomain', $v))->exists()) {
                    $fail('That address is taken — try another.');
                }
            }],
        ], ['subdomain.regex' => 'Use lowercase letters, numbers and dashes only.']);

        $company->forceFill(['requested_subdomain' => $data['subdomain'], 'subdomain_requested_at' => now()])->save();
        Audit::record('workspace.private_requested', $company, "Requested private workspace {$data['subdomain']}." . Tenancy::centralDomain(), [], $company->id);

        return back()->with('success', 'Request sent! We\'ll set up ' . $data['subdomain'] . '.' . Tenancy::centralDomain() . ' and email you when it\'s ready — usually within one working day.');
    }

    public function pdf(Invoice $invoice)
    {
        $company = Company::on(Tenancy::landlordConnection())->findOrFail($invoice->company_id);

        return response(BillingMail::pdf($invoice, $company), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $invoice->number . '.pdf"',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Paying                                                              */
    /* ------------------------------------------------------------------ */

    public function payMobile(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'network' => 'required|in:' . implode(',', array_keys(config('dpo.networks'))),
            'phone'   => ['required', 'string', 'regex:/^(\+?260|0)?[79]\d{8}$/'],
        ], ['phone.regex' => 'Enter a Zambian mobile number, e.g. 0977 123 456.']);

        if ($error = $this->cannotPay($invoice)) {
            return response()->json(['message' => $error], 422);
        }

        $phone = '260' . substr(preg_replace('/\D/', '', $data['phone']), -9);
        [$payment, $error] = $this->startPayment($invoice, 'mobile_money', ['phone' => $phone, 'network' => $data['network']]);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $charge = $this->dpo->chargeMobile($payment->trans_token, $phone, $data['network']);
        if (! $charge['ok']) {
            $payment->forceFill(['status' => 'failed', 'result_explanation' => $charge['message']])->save();

            return response()->json(['message' => $charge['message']], 422);
        }

        return response()->json([
            'payment' => $payment->id,
            'status_url' => route('billing.payments.status', $payment),
            'message' => $charge['instructions'] ?: 'We\'ve sent a payment prompt to ' . $data['phone'] . '. Enter your PIN on your phone to approve.',
        ]);
    }

    public function payCard(Request $request, Invoice $invoice)
    {
        if ($error = $this->cannotPay($invoice)) {
            return response()->json(['message' => $error], 422);
        }

        [$payment, $error] = $this->startPayment($invoice, 'card');
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json([
            'payment' => $payment->id,
            'url' => $this->dpo->payUrl($payment->trans_token),
            'status_url' => route('billing.payments.status', $payment),
        ]);
    }

    /** Polled by the payment window every few seconds. */
    public function status(BillingPayment $payment)
    {
        if ($payment->status === 'pending' && (! $payment->last_checked_at || $payment->last_checked_at->lt(now()->subSeconds(4)))) {
            $this->subs->refresh($payment, $this->dpo);
            $payment->refresh();
        }

        return response()->json([
            'status'  => $payment->status,
            'message' => match ($payment->status) {
                'paid' => 'Payment received — thank you! 🎉',
                'failed' => $payment->result_explanation ?: 'The payment didn\'t go through.',
                'cancelled' => 'The payment was cancelled.',
                default => 'Waiting for approval…',
            },
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  DPO callbacks (no sign-in: the token is verified with DPO)          */
    /* ------------------------------------------------------------------ */

    /** The card popup lands here after paying (or cancelling). */
    public function dpoReturn(Request $request)
    {
        $token = (string) ($request->query('TransactionToken') ?? $request->query('ID') ?? '');
        $payment = $token ? BillingPayment::withoutGlobalScopes()->where('trans_token', $token)->first() : null;
        $status = $payment ? $this->subs->refresh($payment, $this->dpo) : 'unknown';

        return view('billing.dpo-return', ['status' => $status]);
    }

    /** Server-to-server notification from DPO. */
    public function dpoNotify(Request $request)
    {
        $token = $request->input('TransactionToken');
        if (! $token && ($xml = @simplexml_load_string((string) $request->getContent(), 'SimpleXMLElement', LIBXML_NONET))) {
            $token = (string) ($xml->TransactionToken ?? $xml->TransToken ?? '');
        }

        if ($token && ($payment = BillingPayment::withoutGlobalScopes()->where('trans_token', $token)->first())) {
            $this->subs->refresh($payment, $this->dpo);
        }

        return response('OK', 200);
    }

    /* ------------------------------------------------------------------ */

    private function cannotPay(Invoice $invoice): ?string
    {
        if ($invoice->status !== 'open') {
            return 'This invoice is already ' . $invoice->status . '.';
        }
        if (! $this->dpo->configured()) {
            return 'Online payments aren\'t switched on yet. Please contact ZamPayroll support.';
        }

        return null;
    }

    /** @return array{0: ?BillingPayment, 1: ?string} */
    private function startPayment(Invoice $invoice, string $method, array $extra = []): array
    {
        // Don't start a second payment while one is already being approved.
        $pending = BillingPayment::query()->where('invoice_id', $invoice->id)->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(3))->first();
        if ($pending && $pending->method === 'mobile_money' && $method === 'mobile_money') {
            return [null, 'A mobile money prompt was sent less than 3 minutes ago. Approve it on your phone or wait a moment.'];
        }

        $payment = BillingPayment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'provider'   => $this->dpo->fake() ? 'dpo-test' : 'dpo',
            'method'     => $method,
            'amount'     => $invoice->total,
            'currency'   => $invoice->currency,
            'status'     => 'pending',
            'user_id'    => auth()->id(),
        ] + $extra);

        $token = $this->dpo->createToken($invoice, $payment, route('billing.dpo.return'), route('billing.index'));
        if (! $token['ok']) {
            $payment->forceFill(['status' => 'failed', 'result_explanation' => $token['message']])->save();

            return [null, $token['message']];
        }

        $payment->forceFill(['trans_token' => $token['token'], 'trans_ref' => $token['ref'] ?? null])->save();
        Audit::record('billing.payment_started', $payment, "{$method} payment of K " . number_format((float) $payment->amount, 2) . " started for {$invoice->number}", [], $invoice->company_id);

        return [$payment, null];
    }
}
