<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeePortalWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public string $companyName,
        public string $grantedBy,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->companyName} self-service portal is ready 🎉");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.portal-welcome', with: [
            'firstName' => strtok($this->user->name, ' '),
        ]);
    }
}
