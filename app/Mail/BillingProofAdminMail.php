<?php

namespace App\Mail;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells ZamPayroll's billing team that a company has uploaded proof of payment. */
class BillingProofAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BillingPayment $payment, public Invoice $invoice, public ?Company $company)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Proof of payment to check: ' . $this->invoice->number . ' · ' . ($this->company?->name ?? 'a company'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing-proof-admin');
    }

    public function attachments(): array
    {
        if (! $this->payment->proof_path) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->payment->proof_path)->as('proof-' . $this->invoice->number . '.' . pathinfo($this->payment->proof_path, PATHINFO_EXTENSION))];
    }
}
