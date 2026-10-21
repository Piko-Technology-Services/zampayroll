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
            'invoice'  => "Your ZamPayroll invoice {$n}",
            'receipt'  => "Payment received — thank you ({$n})",
            'reminder' => "Reminder: invoice {$n} is overdue",
            'trial'    => ($this->data['days_left'] ?? 0) > 0 ? 'Your ZamPayroll free trial ends in ' . $this->data['days_left'] . ' days' : 'Your ZamPayroll free trial has ended',
            'limited'  => 'Your ZamPayroll account has been limited',
            'workspace' => 'Your private ZamPayroll workspace is ready',
            default    => 'ZamPayroll billing',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.billing');
    }

    public function attachments(): array
    {
        if (! $this->invoice) {
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
