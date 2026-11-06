<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\BillingMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ZamPayroll admin: review proof of payment for manual (bank / mobile money) invoices.
 */
class PaymentProofController extends Controller
{
    public function __construct(private Subscriptions $subs)
    {
    }

    /** The uploaded proof (private storage, so only signed-in ZamPayroll admins can open it). */
    public function file(BillingPayment $payment)
    {
        abort_if(! $payment->proof_path || ! Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function confirm(Request $request, BillingPayment $payment)
    {
        abort_if($payment->status !== 'awaiting_review', 422, 'This proof has already been reviewed.');

        if (! $this->subs->confirmProof($payment, $request->user()?->id)) {
            return back()->with('error', 'This invoice was already paid, so the proof has been kept on record as a duplicate.');
        }

        $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);

        return back()->with('success', 'Payment confirmed for ' . $invoice?->number . '. The plan is now active.');
    }

    public function decline(Request $request, BillingPayment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Give the reason the proof was declined, so the customer knows what to fix.',
            'reason.min'      => 'Give a little more detail for the customer.',
        ]);

        $this->subs->declineProof($payment, $data['reason'], $request->user()?->id);

        return back()->with('success', 'Proof declined. The customer has been emailed the reason and can upload a new one.');
    }
}
