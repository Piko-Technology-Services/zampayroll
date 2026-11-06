<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Every Compliance Centre email: contracts, approvals, policies and reminders, in ZamPayroll's branded layout. */
class ComplianceMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, string|null> $rows */
    public function __construct(
        public string $event,
        public string $mailSubject,
        public string $eyebrow,
        public string $heading,
        public string $intro,
        public array $rows = [],
        public ?string $url = null,
        public ?string $label = null,
        public ?string $attachmentBytes = null,
        public ?string $attachmentName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.compliance');
    }

    public function attachments(): array
    {
        return $this->attachmentBytes
            ? [Attachment::fromData(fn () => $this->attachmentBytes, $this->attachmentName ?? 'document.pdf')->withMime('application/pdf')]
            : [];
    }
}
