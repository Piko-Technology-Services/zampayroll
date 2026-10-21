<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    /** $kind: new | reply (to ZamPayroll) | staff_reply (to the customer) */
    public function __construct(public SupportTicket $ticket, public string $kind)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'new' => "[{$this->ticket->number}] New ticket: {$this->ticket->subject}",
            'staff_reply' => "Re: {$this->ticket->subject} [{$this->ticket->number}]",
            default => "[{$this->ticket->number}] Customer replied: {$this->ticket->subject}",
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-ticket');
    }
}
