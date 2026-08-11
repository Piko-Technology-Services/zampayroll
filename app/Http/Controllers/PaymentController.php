<?php

namespace App\Http\Controllers;

use App\Mail\PaymentSubmissionAdminNotification;
use App\Mail\PaymentSubmissionConfirmation;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Show the payments page (method tabs + Mobile Money submission form).
     */
    public function index()
    {
        return view('payments', [
            'services' => Payment::services(),
        ]);
    }

    /**
     * Handle a new Mobile Money proof-of-payment submission.
     */
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'company_name'      => ['required', 'string', 'max:150'],
        'contact_email'     => ['required', 'email', 'max:180'],
        'contact_phone'     => ['required', 'string', 'max:30'],
        'service'           => ['required', 'string', 'in:' . implode(',', Payment::services())],
        'amount'            => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        'confirmed_sent'    => ['required', 'accepted'],
        'proof_of_payment'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        'comment'           => ['nullable', 'string', 'max:2000'],
        'website'           => ['prohibited'],
    ], [
        'company_name.required'      => 'Please tell us your company name.',
        'contact_email.required'     => 'Please enter a contact email address.',
        'contact_phone.required'     => 'Please enter the phone number the payment was sent from.',
        'service.required'           => 'Please select what this payment is for.',
        'confirmed_sent.accepted'    => 'Please confirm you have sent the payment before submitting.',
        'proof_of_payment.required'  => 'Please attach your proof of payment (screenshot or PDF).',
        'proof_of_payment.mimes'     => 'Proof of payment must be a JPG, PNG or PDF file.',
        'proof_of_payment.max'       => 'Proof of payment must be smaller than 5MB.',
    ]);

    $proofPath = $request->file('proof_of_payment')
        ->store('payment-proofs', 'public');

    $payment = Payment::create([
        'company_name'   => $validated['company_name'],
        'contact_email'  => $validated['contact_email'],
        'contact_phone'  => $validated['contact_phone'],
        'service'        => $validated['service'],
        'amount'         => $validated['amount'] ?? null,
        'method'         => 'mobile_money',
        'proof_path'     => $proofPath,
        'confirmed_sent' => true,
        'comment'        => $validated['comment'] ?? null,
    ]);

    // Notify the ZamPayroll team.
    try {
        $adminAddresses = collect([
            'margie@zampayroll.com',
            'billing@zampayroll.com',
            'katongobupe444@gmail.com',
        ])
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();

        if (!empty($adminAddresses)) {
            Mail::to($adminAddresses)
                ->send(new PaymentSubmissionAdminNotification($payment));

            $payment->forceFill([
                'admin_notified_at' => now(),
            ])->save();
        } else {
            Log::warning(
                'No payment notification addresses configured — admin notification not sent for payment #' . $payment->id
            );
        }
    } catch (\Throwable $e) {
        Log::error(
            'Failed to send payment admin notification: ' . $e->getMessage()
        );
    }

    // Confirm to the customer.
    try {
        Mail::to($payment->contact_email)
            ->send(new PaymentSubmissionConfirmation($payment));

        $payment->forceFill([
            'user_notified_at' => now(),
        ])->save();
    } catch (\Throwable $e) {
        Log::error(
            'Failed to send payment confirmation: ' . $e->getMessage()
        );
    }

    return redirect()
        ->route('payments.index')
        ->with('payment_success', true);
    }
}
