<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken,
        public Company $company,
    ) {
        $this->acceptUrl = route('invitations.accept', $this->rawToken);
    }

    public function build()
    {
        return $this->subject("You've been invited to join {$this->company->name}")
            ->view('emails.invitation')
            ->with([
                'acceptUrl'  => $this->acceptUrl,
                'company'    => $this->company,
                'invitation' => $this->invitation,
            ]);
    }
}
