<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CompanyCodeReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $companies)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ZamPayroll company code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.company-code');
    }
}
