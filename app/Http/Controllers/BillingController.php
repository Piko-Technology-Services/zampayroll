<?php

namespace App\Http\Controllers;

use App\Mail\BillingMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Billing\DpoClient;
use App\Services\Billing\Plans;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $invoices = Invoice::query()->latest('id')->get();
        $awaiting = BillingPayment::query()->where('status', 'awaiting_review')->get()->keyBy('invoice_id');
        $declined = BillingPayment::query()->where('status', 'declined')->latest('id')->get()->unique('invoice_id')->keyBy('invoice_id');

        return view('billing.index', [
            'company'     => $company,
            'state'       => $state,
            'invoices'    => $invoices,
            'payments'    => BillingPayment::query()->latest('id')->take(20)->get(),
            'prices'      => PlatformSettings::all(),
            'plans'       => Plans::cards(),
            'method'      => $request->query('method') === 'online' ? 'online' : 'manual',
            'cycle'       => $request->query('cycle') === 'annual' ? 'annual' : 'monthly',
            'awaiting'    => $awaiting,
            'declined'    => $declined,
            'bank'        => config('billing.bank'),
            'mobileMoney' => collect(config('billing.mobile_money'))->filter(fn ($m) => ! empty($m['number']))->values(),
        ]);
    }

    /**
     * Step 2 and 3 of paying: confirm the details for the receipt and choose how to pay.
     * Manual invoice is selected by default.
     */
    public function checkoutForm(Request $request, string $plan)
    {
        abort_unless(isset(Plans::TIERS[$plan]), 404);
        $company = $this->subs->company();
        $cycle = $request->query('cycle') === 'annual' ? 'annual' : 'monthly';

        return view('billing.checkout', [
            'company'      => $company,
            'planKey'      => $plan,
            'plan'         => Plans::cards()[$plan],
            'cycle'        => $cycle,
            'contactName'  => $request->user()?->name,
            'billingEmail' => $company->billing_email ?: $request->user()?->email,
        ]);
    }

    /**
     * Pay for a plan. Manual: the invoice (with our bank details) is emailed straight away and the
     * customer pays, then uploads proof on the billing page. Online: off to the secure payment page.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan'          => 'required|in:' . implode(',', array_keys(Plans::TIERS)),
            'cycle'         => 'required|in:monthly,annual',
            'method'        => 'required|in:manual,online',
            'contact_name'  => 'nullable|string|max:120',
            'billing_email' => 'nullable|required_if:method,manual|email|max:180',
        ], ['billing_email.required_if' => 'Enter the email address for your invoice and receipt.']);
        $company = $this->subs->company();

        try {
            $invoice = $this->subs->checkoutInvoice($company, $data['plan'], $data['cycle'], $data['method']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! empty($data['billing_email'])) {
            $this->saveBillingContact($company, $data['billing_email'], $data['contact_name'] ?? null, 'Checkout for ' . $invoice->number);
        }
        if (! empty($data['contact_name'])) {
            $invoice->forceFill(['meta' => ['contact_name' => $data['contact_name']] + ($invoice->meta ?? [])])->save();
        }

        if ($data['method'] === 'online') {
            return redirect()->away(Plans::onlineCheckoutUrl($data['plan'], $data['cycle'], array_filter([
                'invoice'      => $invoice->number,
                'amount'       => number_format((float) $invoice->total, 2, '.', ''),
                'currency'     => $invoice->currency,
                'company'      => $company->name,
                'company_code' => $company->company_code,
                'name'         => $data['contact_name'] ?? null,
                'email'        => $data['billing_email'] ?? $company->billing_email,
            ])));
        }

        $this->subs->email($invoice, 'invoice');
        Audit::record('billing.invoice_sent', $invoice, "Invoice {$invoice->number} emailed to {$data['billing_email']}", [], $company->id);

        return redirect()->to(route('billing.index') . '#pay')
            ->with('success', "Invoice {$invoice->number} is on its way to {$data['billing_email']}. Pay it using the details below, then upload your proof of payment.");
    }

    /** Manual: confirm the email the invoice goes to. */
    public function confirmManual(Invoice $invoice)
    {
        abort_if($invoice->status !== 'open', 422, 'This invoice is already ' . $invoice->status . '.');
        $company = $this->subs->company();

        return view('billing.checkout-confirm', [
            'company'      => $company,
            'invoice'      => $invoice,
            'billingEmail' => $company->billing_email ?: (DB::table('users')->where('company_id', $company->id)->where('role', 'company_admin')->value('email')),
            'bank'         => config('billing.bank'),
            'mobileMoney'  => collect(config('billing.mobile_money'))->filter(fn ($m) => ! empty($m['number']))->values(),
        ]);
    }

    /** Manual: save the billing email and email the invoice (PDF with bank details). */
    public function sendManual(Request $request, Invoice $invoice)
    {
        abort_if($invoice->status !== 'open', 422, 'This invoice is already ' . $invoice->status . '.');
        $data = $request->validate(['billing_email' => 'required|email|max:180'], ['billing_email.required' => 'Enter the email address the invoice should go to.']);

        $company = $this->subs->company();
        $this->saveBillingContact($company, $data['billing_email'], null, 'Invoice ' . $invoice->number);
        $this->subs->email($invoice, 'invoice');
        Audit::record('billing.invoice_sent', $invoice, "Invoice {$invoice->number} emailed to {$data['billing_email']}", [], $company->id);

        return redirect()->route('billing.index')->with('success', "Invoice {$invoice->number} has been sent to {$data['billing_email']}. Pay it, then upload your proof of payment below.");
    }

    /** Manual: the customer uploads proof of payment for an open invoice. */
    public function submitProof(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'proof'     => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'reference' => 'nullable|string|max:120',
        ], [
            'proof.required' => 'Attach your proof of payment (a screenshot or PDF of the transfer).',
            'proof.mimes'    => 'Proof of payment must be a JPG, PNG or PDF file.',
            'proof.max'      => 'Proof of payment must be smaller than 5MB.',
        ]);

        try {
            $this->subs->submitProof($invoice, $request->file('proof'), $data['reference'] ?? null, $request->user()?->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('billing.index')->with('success', 'Thank you — your proof of payment has been sent. We\'ll confirm it once the money has arrived.');
    }

    /** Business (post-paid) is no longer offered to new companies. */
    public function postpaid(Request $request)
    {
        return back()->with('error', 'The Business plan has been replaced. Choose a plan below.');
    }

    public function details(Request $request)
    {
        $data = $request->validate(['billing_email' => 'nullable|email|max:180']);
        $company = $this->subs->company();
        $this->saveBillingContact($company, $data['billing_email'] ?? null, null, 'Billing settings');
        Audit::record('billing.details_updated', $company, 'Billing email set to ' . ($data['billing_email'] ?? '—'), [], $company->id);

        return back()->with('success', 'Billing email saved.');
    }

    /** Save the company's billing email and tell the ZamPayroll team when it changes. */
    private function saveBillingContact($company, ?string $email, ?string $contactName, string $where): void
    {
        $old = $company->billing_email;
        $company->forceFill(['billing_email' => $email])->save();

        if (strcasecmp((string) $old, (string) $email) === 0) {
            return;
        }

        $user = request()->user();
        \App\Support\AdminAlert::send(
            'Billing contact updated: ' . $company->name,
            'Billing',
            $company->name . ' changed their billing contact',
            'A client has updated the email address their invoices and receipts go to.',
            [
                'Company'       => $company->name,
                'Company code'  => $company->company_code,
                'New email'     => $email ?: '(removed)',
                'Previous email'=> $old ?: '(none)',
                'Contact name'  => $contactName,
                'Changed by'    => $user ? $user->name . ' · ' . $user->email : null,
                'Where'         => $where,
            ],
            route('platform.companies.show', $company),
            'View company',
        );
    }

    /** Premium companies (over the Starter limit) can ask for their own private sub-domain. */
    public function requestPrivate(Request $request)
    {
        $company = $this->subs->company();
        $state = $this->subs->state($company);
        abort_if($company->isDedicated(), 422, 'You already have a private workspace.');

        if (! $this->subs->qualifiesForPrivate($company, $state['employees'])) {
            return back()->with('error', 'Private workspaces are for companies with more than ' . PlatformSettings::get('free_limit') . ' employees.');
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
