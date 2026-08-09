<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public DemoRequest $demoRequest;

    public function __construct(DemoRequest $demoRequest)
    {
        $this->demoRequest = $demoRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We\'ve received your ZamPayroll demo request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo-request-confirmation',
            with: [
                'demoRequest' => $this->demoRequest,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
