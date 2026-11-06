<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A short heads-up to the ZamPayroll team: new sign-ups, partner applications, billing contact changes. */
class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, string|null> $rows label => value */
    public function __construct(
        public string $alertSubject,
        public string $eyebrow,
        public string $heading,
        public string $intro,
        public array $rows = [],
        public ?string $url = null,
        public ?string $label = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-alert');
    }
}
