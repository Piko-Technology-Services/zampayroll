<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invoices, receipts, reminders and trial notices — with the invoice PDF attached. */
class BillingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kind, public Company $company, public ?Invoice $invoice = null, public array $data = [])
    {
    }

    public function envelope(): Envelope
    {
        $n = $this->invoice?->number;

        return new Envelope(subject: match ($this->kind) {
            'invoice'        => "Your ZamPayroll invoice {$n}",
            'receipt'        => "Payment received — thank you ({$n})",
            'reminder'       => "Reminder: invoice {$n} is overdue",
            'proof_received' => "We've received your proof of payment ({$n})",
            'proof_declined' => "Proof of payment not accepted for {$n}",
            'trial'          => ($this->data['days_left'] ?? 0) > 0 ? 'Your ZamPayroll free experience ends in ' . $this->data['days_left'] . ' days' : 'Your ZamPayroll free experience has ended',
            'limited'        => 'Your ZamPayroll account has been limited',
            'trial_started'  => 'Your ' . ($this->data['months'] ?? 2) . '-month free ZamPayroll experience has started',
            'workspace'      => 'Your private ZamPayroll workspace is ready',
            default          => 'ZamPayroll billing',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing');
    }

    public function attachments(): array
    {
        // The PDF goes with invoices and receipts, not with status messages.
        if (! $this->invoice || ! in_array($this->kind, ['invoice', 'receipt', 'reminder', 'proof_received'], true)) {
            return [];
        }

        $invoice = $this->invoice;
        $company = $this->company;

        return [Attachment::fromData(fn () => self::pdf($invoice, $company), $invoice->number . '.pdf')->withMime('application/pdf')];
    }

    public static function pdf(Invoice $invoice, Company $company): string
    {
        return Pdf::loadView('billing.invoice-pdf', ['invoice' => $invoice, 'company' => $company])->setPaper('a4')->output();
    }
}
