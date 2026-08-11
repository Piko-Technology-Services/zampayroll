<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PaymentSubmissionAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Payment Submission — ' . $this->payment->company_name . ' (' . $this->payment->service . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-submission-admin',
            with: [
                'payment' => $this->payment,
            ],
        );
    }

    public function attachments(): array
    {
        // Attach the proof of payment directly so reviewers don't have to click through.
        if (Storage::disk('public')->exists($this->payment->proof_path)) {
            return [
                Attachment::fromStorageDisk('public', $this->payment->proof_path)
                    ->as('proof-of-payment-' . $this->payment->id . '.' . pathinfo($this->payment->proof_path, PATHINFO_EXTENSION)),
            ];
        }

        return [];
    }
}
