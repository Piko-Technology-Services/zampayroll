<?php

namespace App\Services\Billing;

use App\Models\BillingPayment;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin client for DPO Pay API v6 (XML over HTTPS).
 *
 *  createToken  → a transaction token for an invoice (card popup or mobile money)
 *  chargeMobile → push a mobile-money prompt to the customer's phone (stays in-app)
 *  verifyToken  → the source of truth: only "000" means paid
 *
 * With DPO_FAKE=true (local/testing only) no network call is made and every
 * payment is approved — never enable that in production.
 */
class DpoClient
{
    public const PAID = '000';
    public const PENDING_CODES = ['900', '003', '130'];

    public function configured(): bool
    {
        return $this->fake() || (filled(config('dpo.company_token')) && filled(config('dpo.service_type')));
    }

    public function fake(): bool
    {
        return (bool) config('dpo.fake') && ! app()->isProduction();
    }

    public function payUrl(string $token): string
    {
        if ($this->fake()) {
            return route('billing.dpo.return', ['TransactionToken' => $token]);
        }

        return $this->endpoint('pay') . urlencode($token);
    }

    /**
     * @return array{ok: bool, token?: string, ref?: string, message: string}
     */
    public function createToken(Invoice $invoice, BillingPayment $payment, string $redirectUrl, string $backUrl): array
    {
        if ($this->fake()) {
            return ['ok' => true, 'token' => 'FAKE-' . Str::upper(Str::random(16)), 'ref' => 'R' . random_int(100000, 999999), 'message' => 'Created'];
        }

        $xml = $this->envelope('createToken', '
            <Transaction>
                <PaymentAmount>' . number_format((float) $payment->amount, 2, '.', '') . '</PaymentAmount>
                <PaymentCurrency>' . e($payment->currency) . '</PaymentCurrency>
                <CompanyRef>' . e($invoice->number . '-' . $payment->id) . '</CompanyRef>
                <RedirectURL>' . e($redirectUrl) . '</RedirectURL>
                <BackURL>' . e($backUrl) . '</BackURL>
                <CompanyRefUnique>1</CompanyRefUnique>
                <PTL>' . (int) config('dpo.ptl', 5) . '</PTL>
                <customerEmail>' . e($invoice->company?->billing_email ?: $invoice->company?->email) . '</customerEmail>
            </Transaction>
            <Services>
                <Service>
                    <ServiceType>' . e(config('dpo.service_type')) . '</ServiceType>
                    <ServiceDescription>' . e(Str::limit('ZamPayroll ' . $invoice->number . ' — ' . $invoice->title(), 90, '')) . '</ServiceDescription>
                    <ServiceDate>' . now()->format('Y/m/d H:i') . '</ServiceDate>
                </Service>
            </Services>');

        $res = $this->send($xml);

        return ($res['Result'] ?? null) === self::PAID && ! empty($res['TransToken'])
            ? ['ok' => true, 'token' => $res['TransToken'], 'ref' => $res['TransRef'] ?? null, 'message' => $res['ResultExplanation'] ?? 'Created']
            : ['ok' => false, 'message' => $res['ResultExplanation'] ?? 'DPO could not start the payment.'];
    }

    /**
     * Ask DPO to send a mobile-money approval prompt to the phone.
     *
     * @return array{ok: bool, message: string, instructions?: string}
     */
    public function chargeMobile(string $token, string $phone, string $network): array
    {
        if ($this->fake()) {
            return ['ok' => true, 'message' => 'Prompt sent', 'instructions' => 'Approve the payment on your phone.'];
        }

        $mno = config("dpo.networks.{$network}.mno");
        $xml = $this->envelope('ChargeTokenMobile', '
            <TransactionToken>' . e($token) . '</TransactionToken>
            <PhoneNumber>' . e($phone) . '</PhoneNumber>
            <MNO>' . e($mno) . '</MNO>
            <MNOcountry>' . e(config('dpo.mobile_country')) . '</MNOcountry>');

        $res = $this->send($xml);
        $code = (string) ($res['Code'] ?? $res['Result'] ?? '');
        $ok = in_array($code, ['130', '000', '0'], true) || (isset($res['StatusCode']) && in_array((string) $res['StatusCode'], ['130', '000'], true));

        return [
            'ok'           => $ok,
            'message'      => $res['Explanation'] ?? $res['ResultExplanation'] ?? ($ok ? 'Prompt sent' : 'The mobile money request was not accepted.'),
            'instructions' => $res['Instructions'] ?? null,
        ];
    }

    /**
     * @return array{code: string, message: string, paid: bool, pending: bool}
     */
    public function verify(string $token): array
    {
        if ($this->fake()) {
            return ['code' => self::PAID, 'message' => 'Transaction paid', 'paid' => true, 'pending' => false];
        }

        $res = $this->send($this->envelope('verifyToken', '<TransactionToken>' . e($token) . '</TransactionToken>'));
        $code = (string) ($res['Result'] ?? '');

        return [
            'code'    => $code,
            'message' => $res['ResultExplanation'] ?? '',
            'paid'    => $code === self::PAID,
            'pending' => $code === '' || in_array($code, self::PENDING_CODES, true),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function envelope(string $request, string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><API3G><CompanyToken>' . e(config('dpo.company_token')) . '</CompanyToken>'
            . '<Request>' . $request . '</Request>' . $body . '</API3G>';
    }

    private function endpoint(string $which): string
    {
        $mode = config('dpo.mode') === 'live' ? 'live' : 'sandbox';

        return config("dpo.endpoints.{$mode}.{$which}");
    }

    /** POST the XML and return the response as a flat array of the top-level elements. */
    private function send(string $xml): array
    {
        try {
            $response = Http::timeout(30)->withBody($xml, 'application/xml')->post($this->endpoint('api'));
            $body = (string) $response->body();
            $parsed = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

            if ($parsed === false) {
                Log::warning('DPO: unreadable response', ['status' => $response->status(), 'body' => Str::limit($body, 500)]);

                return ['ResultExplanation' => 'DPO did not respond properly. Please try again.'];
            }

            return collect((array) $parsed)->map(fn ($v) => is_object($v) ? json_decode(json_encode($v), true) : (string) $v)->all();
        } catch (\Throwable $e) {
            report($e);

            return ['ResultExplanation' => 'We couldn\'t reach DPO. Check your connection and try again.'];
        }
    }
}
