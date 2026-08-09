<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestAdminNotification extends Mailable
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
            subject: 'New Demo Request — ' . $this->demoRequest->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.demo-request-admin',
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
