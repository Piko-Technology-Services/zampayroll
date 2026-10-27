<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Partner programme emails: welcome / application received / approved / new client / commission paid. */
class PartnerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kind, public Partner $partner, public array $data = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'welcome'    => 'Welcome to the ZamPayroll partner programme — your code is ' . $this->partner->code,
            'received'   => 'We\'ve received your ZamPayroll partner application',
            'approved'   => 'You\'re approved! Your ZamPayroll partner code is ' . $this->partner->code,
            'new_client' => 'New client: ' . ($this->data['company'] ?? 'a company') . ' signed up with your code',
            'paid'       => 'Your ZamPayroll commission has been paid',
            default      => 'ZamPayroll partner programme',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner');
    }
}
